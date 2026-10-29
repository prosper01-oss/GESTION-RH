<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Exports\Pharma\ReapproCommandExcel;
use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Article;
use App\Services\Pharma\ConsommationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReapproController extends Controller
{
    use RepondEnJson;

    /**
     * Suggestions de réapprovisionnement déduites de la Consommation Moyenne
     * Mensuelle (CMM) sur une période : articles actifs dont le stock est à zéro
     * ou sous le seuil. Quantité conseillée = CMM × mois de couverture − stock.
     * Repli : stock cible (qte_max, sinon 2 × seuil) lorsqu'aucune consommation
     * n'est enregistrée sur la période.
     *
     * Paramètres : date_debut, date_fin (défaut : 3 derniers mois), mois_couverture (défaut 3).
     */
    public function index(Request $request): JsonResponse
    {
        [$rows, $resume] = $this->suggestions($request);

        return $this->ok(['data' => $rows, 'resume' => $resume]);
    }

    /** Export Excel du bon de commande à transmettre au fournisseur. */
    public function export(Request $request): BinaryFileResponse
    {
        [$rows, $resume] = $this->suggestions($request);

        $export = new ReapproCommandExcel(
            $rows,
            trim($request->string('fournisseur')->toString()),
            $resume['periode'],
            $resume['mois_couverture'],
        );

        return Excel::download($export, 'commande-fournisseur-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array{nb_jours: int, mois_couverture: int, periode: string, date_debut: string, date_fin: string}}
     */
    private function suggestions(Request $request): array
    {
        $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'mois_couverture' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $fin = $request->filled('date_fin') ? Carbon::parse($request->date('date_fin'))->endOfDay() : Carbon::now();
        $debut = $request->filled('date_debut') ? Carbon::parse($request->date('date_debut'))->startOfDay() : $fin->copy()->subMonths(3);

        $nbJours = ConsommationService::nbJours($debut->copy(), $fin->copy());
        $moisCouverture = $request->integer('mois_couverture', 3) ?: 3;
        $stats = ConsommationService::statsParArticle($debut->copy(), $fin->copy());

        $rows = Article::query()
            ->with('uniteRelation')
            ->where('statut', 1)
            ->where('etat', 1)
            ->whereColumn('stock', '<=', 'seuil')
            ->orderBy('designation')
            ->get()
            ->map(function (Article $article) use ($stats, $nbJours, $moisCouverture): array {
                $cmm = ConsommationService::cmmArticle($stats[$article->id] ?? null, $nbJours);

                if ($cmm > 0) {
                    $manquant = ConsommationService::proposition($cmm, $moisCouverture, (int) $article->stock);
                } else {
                    // Aucune vente sur la période : on retombe sur le stock cible.
                    $manquant = max(0, ($article->qte_max ?? $article->seuil * 2) - $article->stock);
                }

                $unite = $article->uniteRelation;
                $presentation = ($unite && $unite->nomUnite !== 'Unité' && $article->presentation > 1)
                    ? $unite->nomComplet.'/'.$article->presentation
                    : ($unite?->nomComplet ?? '—');

                return [
                    'id' => $article->id,
                    'reference' => 'REF-'.str_pad((string) $article->id, 4, '0', STR_PAD_LEFT),
                    'designation' => $article->designation,
                    'presentation' => $presentation,
                    'stock' => (int) $article->stock,
                    'seuil' => (int) $article->seuil,
                    'cible' => $cmm > 0 ? ceil($cmm * $moisCouverture) : ($article->qte_max ?? $article->seuil * 2),
                    'cmm' => $cmm,
                    'mois_couverture' => $moisCouverture,
                    'manquant' => $manquant,
                    'rupture' => $article->stock == 0,
                    'base' => $cmm > 0 ? 'cmm' : 'cible',
                ];
            })
            ->values()
            ->all();

        $resume = [
            'nb_jours' => $nbJours,
            'mois_couverture' => $moisCouverture,
            'periode' => $debut->format('d/m/Y').' — '.$fin->format('d/m/Y'),
            'date_debut' => $debut->toDateString(),
            'date_fin' => $fin->toDateString(),
        ];

        return [$rows, $resume];
    }
}

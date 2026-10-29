<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Enums\RoleName;
use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharma\StoreArticleRequest;
use App\Models\Pharma\Article;
use App\Models\Pharma\Pourcentage;
use App\Models\Pharma\Unite;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        $peutSupprimer = $this->peutSupprimer();

        $rows = Article::query()
            ->with('uniteRelation')
            ->where('etat', 1)
            ->orderBy('designation')
            ->get()
            ->map(function (Article $article) use ($peutSupprimer): array {
                $unite = $article->uniteRelation;
                $presentation = ($unite && $unite->nomUnite !== 'Unité' && $article->presentation > 1)
                    ? $unite->nomComplet.'/'.$article->presentation
                    : ($unite?->nomComplet ?? '—');

                return [
                    'id' => $article->id,
                    'reference' => 'REF-'.str_pad((string) $article->id, 4, '0', STR_PAD_LEFT),
                    'designation' => $article->designation,
                    'presentation' => $presentation,
                    'presentation_raw' => (int) $article->presentation,
                    'unite_id' => (int) $article->unite,
                    'stock_boites' => $article->stockBoites(),
                    'stock_unites' => (int) $article->stock,
                    'nb_lots' => $article->lotsDisponibles()->count(),
                    'seuil' => (int) $article->seuil,
                    'qte_max' => $article->qte_max === null ? null : (int) $article->qte_max,
                    'cmup' => $article->cmup(),
                    'valeur_stock' => $article->valeurStock(),
                    'statut' => ((int) $article->statut) === 1 ? 'Actif' : 'Inactif',
                    // Alertes : rupture si stock = 0, seuil si stock <= seuil
                    'alerte' => $article->stock == 0 ? 'rupture' : ($article->stock <= $article->seuil ? 'seuil' : null),
                    'peut_supprimer' => $peutSupprimer,
                ];
            });

        return $this->ok(['data' => $rows, 'taux' => Pourcentage::taux()]);
    }

    public function unites(): JsonResponse
    {
        return $this->ok(['data' => Unite::query()->orderBy('nomComplet')->get(['id', 'nomUnite', 'nomComplet', 'supun'])
            ->map(fn (Unite $unite): array => ['id' => $unite->id, 'libelle' => $unite->nomComplet, 'supun' => (bool) $unite->supun])]);
    }

    /** Vue détaillée du stock par lot d'un article. */
    public function stockDetail(Article $article): JsonResponse
    {
        $lots = $article->lotsDisponibles()
            ->orderByRaw('date_peremption IS NULL ASC')
            ->orderBy('date_peremption')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($lot): array => [
                'lot' => $lot->lot,
                'stock_restant_boites' => round($lot->stock_restant_lot / max(1, $article->presentation), 2),
                'stock_restant_unites' => (int) $lot->stock_restant_lot,
                'prix_unitaire' => $lot->prix_unitaire,
                'date_peremption' => $lot->date_peremption?->format('Y-m-d'),
                'date_entree' => $lot->created_at?->format('Y-m-d'),
                'perime' => $lot->estPerime(),
                'peremption_statut' => $lot->statutPeremption(),
            ]);

        return $this->ok([
            'article' => ['designation' => $article->designation],
            'taux' => Pourcentage::taux(),
            'data' => $lots,
        ]);
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        Article::create($request->validated());

        return $this->ok(['message' => 'Article enregistré.'], 201);
    }

    public function update(StoreArticleRequest $request, Article $article): JsonResponse
    {
        abort_unless((int) $article->etat === 1, 404);

        $article->update($request->validated());

        return $this->ok(['message' => 'Article enregistré.']);
    }

    public function destroy(Article $article): JsonResponse
    {
        abort_unless($this->peutSupprimer(), 403);

        $article->update(['etat' => 0]);

        return $this->ok(['message' => 'Article supprimé.']);
    }

    /** Suppression réservée au pharmacien et à l'administrateur. */
    private function peutSupprimer(): bool
    {
        $user = auth()->user();

        return $user->hasRole(RoleName::Pharmacien) || $user->hasRole(RoleName::Administrateur);
    }
}

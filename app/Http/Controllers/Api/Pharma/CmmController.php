<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Article;
use App\Services\Pharma\ConsommationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CmmController extends Controller
{
    use RepondEnJson;

    /**
     * Consommation Moyenne Mensuelle par article sur une période filtrée.
     * Paramètres : date_debut, date_fin (dates), mois_couverture (défaut 3).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_debut' => ['required', 'date', 'before_or_equal:date_fin'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'mois_couverture' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $debut = Carbon::parse($request->date('date_debut'))->startOfDay();
        $fin = Carbon::parse($request->date('date_fin'))->endOfDay();
        $nbJours = ConsommationService::nbJours($debut->copy(), $fin->copy());
        $moisCouverture = $request->integer('mois_couverture', 3) ?: 3;
        $stats = ConsommationService::statsParArticle($debut->copy(), $fin->copy());

        // Uniquement les articles vendus sur la période.
        $rows = Article::query()
            ->with('uniteRelation')
            ->where('etat', 1)
            ->whereIn('id', array_keys($stats))
            ->orderBy('designation')
            ->get()
            ->map(function (Article $article) use ($stats, $nbJours, $moisCouverture): array {
                $qte = $stats[$article->id] ?? 0;
                $cmm = ConsommationService::cmmArticle($qte, $nbJours);
                $unite = $article->uniteRelation;
                $presentation = ($unite && $unite->nomUnite !== 'Unité' && $article->presentation > 1)
                    ? $unite->nomComplet.'/'.$article->presentation
                    : ($unite?->nomComplet ?? '—');

                return [
                    'id' => $article->id,
                    'reference' => 'REF-'.str_pad((string) $article->id, 4, '0', STR_PAD_LEFT),
                    'designation' => $article->designation,
                    'presentation' => $presentation,
                    'qte_vendue' => $qte,
                    'nb_jours' => $nbJours,
                    'cmm' => $cmm,
                    'stock' => (int) $article->stock,
                    'seuil' => (int) $article->seuil,
                    'bientot_seuil' => $article->stock <= $article->seuil,
                    'proposition' => $cmm > 0 ? ConsommationService::proposition($cmm, $moisCouverture, (int) $article->stock) : 0,
                ];
            });

        return $this->ok([
            'data' => $rows,
            'resume' => ['nb_articles' => $rows->count(), 'nb_jours' => $nbJours],
        ]);
    }
}

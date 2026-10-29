<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharma\StoreSortieIndexRequest;
use App\Models\Pharma\SortieDetail;
use App\Models\Pharma\SortieIndex;
use Illuminate\Http\JsonResponse;

/**
 * En-têtes de sortie de stock (ajustements, pertes, casse, péremption).
 */
class SortieIndexController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        $stats = SortieDetail::query()
            ->selectRaw('sortie_indices_id, COUNT(*) as nb_article, COALESCE(SUM(montant_perte), 0) as montant_total')
            ->where('etat', 1)
            ->groupBy('sortie_indices_id')
            ->get()
            ->keyBy('sortie_indices_id');

        $rows = SortieIndex::query()->where('etat', 1)->orderByDesc('created_at')->get()
            ->map(function (SortieIndex $index) use ($stats): array {
                $stat = $stats->get($index->id);

                return [
                    'id' => $index->id,
                    'ref_sortie' => $index->ref_sortie,
                    'motif' => $index->motif,
                    'nb_article' => (int) ($stat->nb_article ?? 0),
                    'montant_perte' => (float) ($stat->montant_total ?? 0),
                    'created_at' => $index->created_at,
                    'peut_supprimer' => ($stat?->nb_article ?? 0) == 0,
                ];
            });

        return $this->ok(['data' => $rows]);
    }

    public function store(StoreSortieIndexRequest $request): JsonResponse
    {
        $sortieIndex = SortieIndex::create($request->validated());

        return $this->ok(['message' => 'Sortie enregistrée.', 'id' => $sortieIndex->id], 201);
    }

    public function update(StoreSortieIndexRequest $request, SortieIndex $sortieIndex): JsonResponse
    {
        abort_unless($sortieIndex->etat, 404);

        $sortieIndex->update($request->validated());

        return $this->ok(['message' => 'Sortie enregistrée.']);
    }

    public function destroy(SortieIndex $sortieIndex): JsonResponse
    {
        if ($sortieIndex->details()->where('etat', 1)->exists()) {
            return $this->echec('Impossible de supprimer : cette sortie contient des lignes.');
        }

        $sortieIndex->update(['etat' => 0]);

        return $this->ok(['message' => 'Sortie supprimée.']);
    }
}

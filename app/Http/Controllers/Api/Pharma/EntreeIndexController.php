<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharma\StoreEntreeIndexRequest;
use App\Models\Pharma\EntreeDetail;
use App\Models\Pharma\EntreeIndex;
use App\Models\Pharma\Pourcentage;
use Illuminate\Http\JsonResponse;

/**
 * En-têtes d'entrée de stock (approvisionnements).
 */
class EntreeIndexController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        // Agrégats recalculés en lecture, en excluant les lignes supprimées logiquement
        $stats = EntreeDetail::query()
            ->selectRaw('entree_indices_id,
                COUNT(*) as nb_article,
                COALESCE(SUM(montant_entree), 0) as montant_total,
                COALESCE(SUM(montant_gain_brut), 0) as montant_gain_brut')
            ->where('etat', 1)
            ->groupBy('entree_indices_id')
            ->get()
            ->keyBy('entree_indices_id');

        $rows = EntreeIndex::query()->with('fournisseur:id,nom')->where('etat', 1)->orderByDesc('created_at')->get()
            ->map(function (EntreeIndex $index) use ($stats): array {
                $stat = $stats->get($index->id);

                return [
                    'id' => $index->id,
                    'ref_entree' => $index->ref_entree,
                    'motif' => $index->motif ?? '',
                    'fournisseur_id' => $index->fournisseur_id,
                    'fournisseur' => $index->fournisseur?->nom ?? '',
                    'nb_article' => (int) ($stat->nb_article ?? 0),
                    'montant_achat' => (float) ($stat->montant_total ?? 0),
                    'montant_estime' => (float) ($stat->montant_gain_brut ?? 0),
                    'created_at' => $index->created_at,
                    'peut_supprimer' => ($stat?->nb_article ?? 0) == 0,
                ];
            });

        return $this->ok(['data' => $rows, 'taux' => Pourcentage::taux()]);
    }

    public function store(StoreEntreeIndexRequest $request): JsonResponse
    {
        $entreeIndex = EntreeIndex::create($request->validated());

        return $this->ok(['message' => "Entrée d'approvisionnement enregistrée.", 'id' => $entreeIndex->id], 201);
    }

    public function update(StoreEntreeIndexRequest $request, EntreeIndex $entreeIndex): JsonResponse
    {
        abort_unless($entreeIndex->etat, 404);

        $entreeIndex->update($request->validated());

        return $this->ok(['message' => "Entrée d'approvisionnement enregistrée."]);
    }

    /** Suppression logique — réservée aux entrées vides. */
    public function destroy(EntreeIndex $entreeIndex): JsonResponse
    {
        if ($entreeIndex->details()->where('etat', 1)->exists()) {
            return $this->echec('Impossible de supprimer : cette entrée contient des lots.');
        }

        $entreeIndex->update(['etat' => 0]);

        return $this->ok(['message' => 'Entrée supprimée.']);
    }
}

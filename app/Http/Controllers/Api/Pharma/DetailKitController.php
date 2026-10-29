<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharma\StoreDetailKitRequest;
use App\Models\Pharma\Article;
use App\Models\Pharma\DetailKit;
use App\Models\Pharma\Kit;
use Illuminate\Http\JsonResponse;

/**
 * Composition des kits : quels articles, en quelle quantité.
 */
class DetailKitController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        $rows = DetailKit::query()
            ->with(['kit:id,nom_kit', 'article:id,designation'])
            ->where('etat', 1)
            ->orderByDesc('id')
            ->get()
            ->map(fn (DetailKit $detail): array => [
                'id' => $detail->id,
                'id_kit' => $detail->id_kit,
                'id_article' => $detail->id_article,
                'kit' => $detail->kit?->nom_kit ?? '—',
                'article' => $detail->article?->designation ?? '—',
                'qte_kit' => (int) $detail->qte_kit,
            ]);

        return $this->ok(['data' => $rows]);
    }

    public function store(StoreDetailKitRequest $request): JsonResponse
    {
        if ($this->doublon($request)) {
            return $this->echec('Cet article est déjà présent dans ce kit.');
        }

        DetailKit::create($request->validated());

        return $this->ok(['message' => 'Composition enregistrée.'], 201);
    }

    public function update(StoreDetailKitRequest $request, DetailKit $detailKit): JsonResponse
    {
        if ($this->doublon($request, $detailKit->id)) {
            return $this->echec('Cet article est déjà présent dans ce kit.');
        }

        $detailKit->update($request->validated());

        return $this->ok(['message' => 'Composition enregistrée.']);
    }

    public function destroy(DetailKit $detailKit): JsonResponse
    {
        $detailKit->update(['etat' => 0]);

        return $this->ok(['message' => 'Ligne de composition supprimée.']);
    }

    /** Sélecteurs du formulaire : kits et articles actifs. */
    public function options(): JsonResponse
    {
        return $this->ok([
            'kits' => Kit::query()->where('etat', 1)->orderBy('nom_kit')->get(['id', 'nom_kit as nom']),
            'articles' => Article::query()->where('etat', 1)->orderBy('designation')->get(['id', 'designation as nom']),
        ]);
    }

    /** Unicité applicative du couple (kit, article) actif. */
    private function doublon(StoreDetailKitRequest $request, ?int $ignorerId = null): bool
    {
        return DetailKit::query()
            ->where('id_kit', $request->integer('id_kit'))
            ->where('id_article', $request->integer('id_article'))
            ->where('etat', 1)
            ->when($ignorerId, fn ($q) => $q->where('id', '!=', $ignorerId))
            ->exists();
    }
}

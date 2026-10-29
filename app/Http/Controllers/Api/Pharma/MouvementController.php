<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\StockMouvement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Journal des mouvements de stock (audit).
 */
class MouvementController extends Controller
{
    use RepondEnJson;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'type' => ['nullable', 'string', 'max:40'],
            'recherche' => ['nullable', 'string', 'max:255'],
        ]);

        $query = StockMouvement::query()->with(['article:id,designation', 'entreeDetail:id,lot', 'user:id,name']);

        if ($request->filled('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date('date_debut'));
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('created_at', '<=', $request->date('date_fin'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $recherche = $request->string('recherche')->trim()->toString();
        if ($recherche !== '') {
            $query->where(function ($q) use ($recherche): void {
                $q->where('reference', 'like', "%{$recherche}%")
                    ->orWhere('libelle', 'like', "%{$recherche}%")
                    ->orWhereHas('article', fn ($a) => $a->where('designation', 'like', "%{$recherche}%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$recherche}%"));
            });
        }

        $rows = $query->orderByDesc('id')->limit(2000)->get()->map(fn (StockMouvement $m): array => [
            'id' => $m->id,
            'date' => $m->created_at,
            'type' => $m->type,
            'qte' => $m->qte,
            'article' => $m->article?->designation ?? '—',
            'lot' => $m->entreeDetail?->lot ?? '',
            'reference' => $m->reference ?? '',
            'libelle' => $m->libelle ?? '',
            'stock_avant' => $m->stock_avant,
            'stock_apres' => $m->stock_apres,
            'user' => $m->user?->name ?? '',
        ]);

        return $this->ok(['data' => $rows]);
    }
}

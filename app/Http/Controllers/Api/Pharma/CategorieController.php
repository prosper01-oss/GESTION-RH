<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Categorie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategorieController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        return $this->ok(['data' => Categorie::query()->where('etat', 1)->orderByDesc('id')->get(['id', 'nom_cat'])]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->enregistrer($request, new Categorie, 201);
    }

    public function update(Request $request, Categorie $categorie): JsonResponse
    {
        return $this->enregistrer($request, $categorie);
    }

    public function destroy(Categorie $categorie): JsonResponse
    {
        $categorie->update(['etat' => 0]);

        return $this->ok(['message' => 'Catégorie supprimée.']);
    }

    private function enregistrer(Request $request, Categorie $categorie, int $status = 200): JsonResponse
    {
        $validated = $request->validate(['nom_cat' => ['required', 'string', 'max:255']]);

        $doublon = Categorie::query()
            ->where('nom_cat', $validated['nom_cat'])
            ->where('etat', 1)
            ->when($categorie->exists, fn ($q) => $q->where('id', '!=', $categorie->id))
            ->exists();

        if ($doublon) {
            return $this->echec('Cette catégorie existe déjà.');
        }

        if ($categorie->exists) {
            $categorie->update($validated);
        } else {
            // Le nom est unique en base : un nom supprimé logiquement est réactivé.
            Categorie::updateOrCreate(['nom_cat' => $validated['nom_cat']], ['etat' => 1]);
        }

        return $this->ok(['message' => 'Catégorie enregistrée.'], $status);
    }
}

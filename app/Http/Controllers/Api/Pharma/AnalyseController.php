<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Analyse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyseController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        return $this->ok(['data' => Analyse::query()->where('etat', 1)->orderByDesc('id')->get(['id', 'nom', 'prix', 'updated_at'])]);
    }

    /** Liste légère pour les sélecteurs (nom, prix). */
    public function options(): JsonResponse
    {
        return $this->ok(['data' => Analyse::query()->where('etat', 1)->orderBy('nom')->get(['id', 'nom', 'prix'])]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->enregistrer($request, new Analyse, 201);
    }

    public function update(Request $request, Analyse $analyse): JsonResponse
    {
        return $this->enregistrer($request, $analyse);
    }

    public function destroy(Analyse $analyse): JsonResponse
    {
        $analyse->update(['etat' => 0]);

        return $this->ok(['message' => 'Analyse supprimée.']);
    }

    private function enregistrer(Request $request, Analyse $analyse, int $status = 200): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prix' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $doublon = Analyse::query()
            ->where('nom', $validated['nom'])
            ->where('etat', 1)
            ->when($analyse->exists, fn ($q) => $q->where('id', '!=', $analyse->id))
            ->exists();

        if ($doublon) {
            return $this->echec('Une analyse porte déjà ce nom.');
        }

        if ($analyse->exists) {
            $analyse->update($validated);
        } else {
            // Le nom est unique en base : un nom supprimé logiquement est réactivé.
            Analyse::updateOrCreate(['nom' => $validated['nom']], ['prix' => $validated['prix'], 'etat' => 1]);
        }

        return $this->ok(['message' => 'Analyse enregistrée.'], $status);
    }
}

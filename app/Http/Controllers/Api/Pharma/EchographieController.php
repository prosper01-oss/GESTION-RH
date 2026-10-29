<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Echographie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EchographieController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        return $this->ok(['data' => Echographie::query()->where('etat', 1)->orderByDesc('id')->get(['id', 'nom', 'prix', 'updated_at'])]);
    }

    /** Liste légère pour les sélecteurs (nom, prix). */
    public function options(): JsonResponse
    {
        return $this->ok(['data' => Echographie::query()->where('etat', 1)->orderBy('nom')->get(['id', 'nom', 'prix'])]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->enregistrer($request, new Echographie, 201);
    }

    public function update(Request $request, Echographie $echographie): JsonResponse
    {
        return $this->enregistrer($request, $echographie);
    }

    public function destroy(Echographie $echographie): JsonResponse
    {
        $echographie->update(['etat' => 0]);

        return $this->ok(['message' => 'Échographie supprimée.']);
    }

    private function enregistrer(Request $request, Echographie $echographie, int $status = 200): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prix' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $doublon = Echographie::query()
            ->where('nom', $validated['nom'])
            ->where('etat', 1)
            ->when($echographie->exists, fn ($q) => $q->where('id', '!=', $echographie->id))
            ->exists();

        if ($doublon) {
            return $this->echec('Une échographie porte déjà ce nom.');
        }

        if ($echographie->exists) {
            $echographie->update($validated);
        } else {
            // Le nom est unique en base : un nom supprimé logiquement est réactivé.
            Echographie::updateOrCreate(['nom' => $validated['nom']], ['prix' => $validated['prix'], 'etat' => 1]);
        }

        return $this->ok(['message' => 'Échographie enregistrée.'], $status);
    }
}

<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Kit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        return $this->ok(['data' => Kit::query()->where('etat', 1)->orderByDesc('id')->get(['id', 'nom_kit', 'prix_kit'])]);
    }

    /** Sélecteur de la caisse : prix transmis pour affichage (recalculé côté serveur à l'encaissement). */
    public function options(): JsonResponse
    {
        return $this->ok(['data' => Kit::query()->where('etat', 1)->orderBy('nom_kit')
            ->get(['id', 'nom_kit', 'prix_kit'])
            ->map(fn (Kit $kit): array => ['id' => $kit->id, 'nom' => $kit->nom_kit, 'prix' => $kit->prix_kit])]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->valider($request);

        if ($this->doublon($validated['nom_kit'])) {
            return $this->echec('Un kit porte déjà ce nom.');
        }

        // Le nom est unique en base : un kit supprimé logiquement est réactivé.
        Kit::updateOrCreate(['nom_kit' => $validated['nom_kit']], ['prix_kit' => $validated['prix_kit'], 'etat' => 1]);

        return $this->ok(['message' => 'Kit enregistré.'], 201);
    }

    public function update(Request $request, Kit $kit): JsonResponse
    {
        $validated = $this->valider($request);

        if ($this->doublon($validated['nom_kit'], $kit->id)) {
            return $this->echec('Un kit porte déjà ce nom.');
        }

        $kit->update($validated);

        return $this->ok(['message' => 'Kit enregistré.']);
    }

    public function destroy(Kit $kit): JsonResponse
    {
        $kit->update(['etat' => 0]);

        return $this->ok(['message' => 'Kit supprimé.']);
    }

    private function doublon(string $nom, ?int $ignorerId = null): bool
    {
        return Kit::query()->where('nom_kit', $nom)->where('etat', 1)
            ->when($ignorerId, fn ($q) => $q->where('id', '!=', $ignorerId))
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function valider(Request $request): array
    {
        return $request->validate([
            'nom_kit' => ['required', 'string', 'max:255'],
            'prix_kit' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);
    }
}

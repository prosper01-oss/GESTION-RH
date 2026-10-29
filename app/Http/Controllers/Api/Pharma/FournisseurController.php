<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Fournisseur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FournisseurController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        $rows = Fournisseur::query()->withCount(['entrees as nb_entrees' => fn ($q) => $q->where('etat', 1)])
            ->orderByDesc('id')->get()
            ->map(fn (Fournisseur $fournisseur): array => [
                'id' => $fournisseur->id,
                'nom' => $fournisseur->nom,
                'telephone' => $fournisseur->telephone ?? '',
                'email' => $fournisseur->email ?? '',
                'adresse' => $fournisseur->adresse ?? '',
                'actif' => $fournisseur->actif,
                'nb_entrees' => $fournisseur->nb_entrees,
            ]);

        return $this->ok(['data' => $rows]);
    }

    /** Référentiel léger pour les sélecteurs (entrées de stock, suggestions). */
    public function options(): JsonResponse
    {
        return $this->ok(['data' => Fournisseur::query()->where('actif', 1)->orderBy('nom')->get(['id', 'nom'])]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->enregistrer($request, new Fournisseur, 201);
    }

    public function update(Request $request, Fournisseur $fournisseur): JsonResponse
    {
        return $this->enregistrer($request, $fournisseur);
    }

    public function destroy(Fournisseur $fournisseur): JsonResponse
    {
        $fournisseur->update(['actif' => 0]);

        return $this->ok(['message' => 'Fournisseur désactivé.']);
    }

    private function enregistrer(Request $request, Fournisseur $fournisseur, int $status = 200): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'email', 'max:120'],
            'adresse' => ['nullable', 'string', 'max:255'],
        ]);

        $doublon = Fournisseur::query()
            ->where('nom', $validated['nom'])
            ->where('actif', 1)
            ->when($fournisseur->exists, fn ($q) => $q->where('id', '!=', $fournisseur->id))
            ->exists();

        if ($doublon) {
            return $this->echec('Ce fournisseur existe déjà.');
        }

        $fournisseur->fill($validated)->save();

        return $this->ok(['message' => 'Fournisseur enregistré.'], $status);
    }
}

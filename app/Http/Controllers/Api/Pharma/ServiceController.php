<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Categorie;
use App\Models\Pharma\Service;
use App\Services\Pharma\Docteurs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ServiceController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        $rows = Service::query()->with('categorie:id,nom_cat')->where('etat', 1)->orderByDesc('id')->get()
            ->map(fn (Service $service): array => [
                'id' => $service->id,
                'categorie_id' => $service->categorie_id,
                'categorie' => $service->categorie?->nom_cat,
                'nom_service' => $service->nom_service,
                'prix_service' => $service->prix_service,
                'isConsult' => (int) $service->isConsult,
                'acte_dentaire' => $service->acte_dentaire,
                'created_at' => $service->created_at,
            ]);

        return $this->ok(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->valider($request);

        if ($this->doublon($validated['nom_service'])) {
            return $this->echec('Un service porte déjà ce nom.');
        }

        // Le nom est unique en base : un service supprimé logiquement est réactivé.
        Service::updateOrCreate(['nom_service' => $validated['nom_service']], $validated + ['etat' => 1]);

        return $this->ok(['message' => 'Service enregistré.'], 201);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $validated = $this->valider($request);

        if ($this->doublon($validated['nom_service'], $service->id)) {
            return $this->echec('Un service porte déjà ce nom.');
        }

        $service->update($validated);

        return $this->ok(['message' => 'Service enregistré.']);
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->update(['etat' => 0]);

        return $this->ok(['message' => 'Service supprimé.']);
    }

    /** Sélecteur des catégories pour le formulaire service. */
    public function categories(): JsonResponse
    {
        return $this->ok(['data' => Categorie::query()->where('etat', 1)->orderBy('nom_cat')->get(['id', 'nom_cat'])]);
    }

    /**
     * Données de l'onglet Imagerie/Consultation à la caisse :
     * - services vendables (isConsult = 0, actifs)
     * - le service consultation (isConsult = 1)
     * - types de consultation (généraliste / dentiste)
     */
    public function options(): JsonResponse
    {
        abort_unless(Gate::any(['pharma.caisse', 'pharma.gestion']), 403);

        $services = Service::query()
            ->with('categorie:id,nom_cat')
            ->where('isConsult', 0)
            ->where('etat', 1)
            ->orderBy('nom_service')
            ->get(['id', 'categorie_id', 'nom_service', 'prix_service']);

        $consultation = Service::query()
            ->where('isConsult', 1)
            ->where('etat', 1)
            ->orderBy('id')
            ->first(['id', 'nom_service', 'prix_service']);

        return $this->ok([
            'services' => $services->map(fn (Service $s): array => ['id' => $s->id, 'nom' => $s->nom_service, 'prix' => $s->prix_service, 'categorie' => $s->categorie?->nom_cat])->values(),
            'consultation' => $consultation ? ['id' => $consultation->id, 'nom' => $consultation->nom_service, 'prix' => $consultation->prix_service] : null,
            'types_docteur' => [
                ['valeur' => Docteurs::GENERALISTE, 'libelle' => 'Généraliste'],
                ['valeur' => Docteurs::DENTISTE, 'libelle' => 'Dentiste'],
            ],
        ]);
    }

    private function doublon(string $nom, ?int $ignorerId = null): bool
    {
        return Service::query()->where('nom_service', $nom)->where('etat', 1)
            ->when($ignorerId, fn ($q) => $q->where('id', '!=', $ignorerId))
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function valider(Request $request): array
    {
        $validated = $request->validate([
            'categorie_id' => ['required', 'integer', 'exists:pharma_categories,id'],
            'nom_service' => ['required', 'string', 'max:255'],
            'prix_service' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'isConsult' => ['nullable', 'boolean'],
            'acte_dentaire' => ['nullable', 'boolean'],
        ]);
        $validated['isConsult'] = (int) $request->boolean('isConsult');
        $validated['acte_dentaire'] = $request->boolean('acte_dentaire');

        return $validated;
    }
}

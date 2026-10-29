<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Enums\RoleName;
use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Consultation;
use App\Models\Pharma\OrdreSoins;
use App\Models\Pharma\Panier;
use App\Models\Pharma\Service;
use App\Services\Pharma\Docteurs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Soins prescrits par le dentiste : il choisit des actes (carie, détartrage…) pour le patient qu'il a reçu,
 * et le patient les règle ensuite en caisse.
 */
class OrdreSoinsController extends Controller
{
    use RepondEnJson;

    /** Actes que le dentiste peut prescrire : les services marqués « acte dentaire », avec leur prix. */
    public function actes(): JsonResponse
    {
        return $this->ok(['data' => Service::query()
            ->where(['etat' => true, 'acte_dentaire' => true])
            ->orderBy('nom_service')
            ->get(['id', 'nom_service', 'prix_service'])
            ->map(fn (Service $service): array => ['id' => $service->id, 'nom' => $service->nom_service, 'prix' => $service->prix_service])]);
    }

    public function store(Request $request, Consultation $consultation): JsonResponse
    {
        abort_unless($this->peutAgir($request, $consultation), 403);

        $validated = $request->validate([
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.service_id' => ['required', 'integer', 'distinct'],
            'lignes.*.qte' => ['required', 'integer', 'min:1', 'max:99'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['lignes.min' => 'Choisissez au moins un acte.', 'lignes.required' => 'Choisissez au moins un acte.']);

        if ($consultation->type_docteur !== Docteurs::DENTISTE) {
            return $this->echec('Seul le dentiste envoie des soins en caisse.', 403);
        }

        $services = Service::query()
            ->where(['etat' => true, 'acte_dentaire' => true])
            ->whereIn('id', collect($validated['lignes'])->pluck('service_id'))
            ->get()
            ->keyBy('id');

        if ($services->count() !== count($validated['lignes'])) {
            return $this->echec("Un des actes choisis n'est plus disponible.");
        }

        $ordre = DB::transaction(function () use ($validated, $consultation, $request): OrdreSoins {
            $ordre = OrdreSoins::create([
                'consultation_id' => $consultation->id,
                'user_id' => $request->user()->id,
                'statut' => OrdreSoins::EN_ATTENTE,
                'note' => $validated['note'] ?? null,
            ]);

            foreach ($validated['lignes'] as $ligne) {
                $ordre->lignes()->create(['service_id' => $ligne['service_id'], 'qte' => $ligne['qte']]);
            }

            // Envoyer le patient en caisse suppose qu'il a été reçu.
            if ($consultation->statut === Consultation::EN_ATTENTE) {
                $consultation->update(['statut' => Consultation::VU, 'vu_at' => now()]);
            }

            return $ordre;
        });

        return $this->ok(['message' => 'Patient envoyé en caisse.', 'id' => $ordre->id, 'total' => $ordre->load('lignes.service')->total()], 201);
    }

    /** Annule des soins que la caisse n'a pas encore encaissés. */
    public function destroy(Request $request, OrdreSoins $ordreSoins): JsonResponse
    {
        abort_unless($this->peutAgir($request, $ordreSoins->consultation), 403);

        if ($ordreSoins->statut !== OrdreSoins::EN_ATTENTE) {
            return $this->echec('Ces soins sont déjà réglés ou annulés.');
        }

        DB::transaction(function () use ($ordreSoins): void {
            Panier::query()->where('ordre_soins_id', $ordreSoins->id)->delete();
            $ordreSoins->update(['statut' => OrdreSoins::ANNULE]);
        });

        return $this->ok(['message' => 'Envoi en caisse annulé.']);
    }

    /** Le docteur de la consultation, ou un administrateur. */
    private function peutAgir(Request $request, Consultation $consultation): bool
    {
        return $request->user()->hasRole(RoleName::Administrateur)
            || (string) $consultation->docteur === (string) $request->user()->employee_id;
    }
}

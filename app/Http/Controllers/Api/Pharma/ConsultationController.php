<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Enums\RoleName;
use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Consultation;
use App\Models\Pharma\OrdreSoins;
use App\Services\Pharma\Docteurs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * File d'attente des patients adressés par la caisse : chaque docteur ou dentiste voit les patients
 * dont la consultation a été payée à son nom ; un administrateur voit ceux de tous les docteurs.
 */
class ConsultationController extends Controller
{
    use RepondEnJson;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'statut' => ['nullable', 'in:en_attente,vu'],
        ]);

        $debut = Carbon::parse($validated['date_debut'] ?? today())->startOfDay();
        $fin = Carbon::parse($validated['date_fin'] ?? today())->endOfDay();
        $voitTout = $this->voitTout($request);

        if (! $voitTout && ! $request->user()->employee_id) {
            return $this->ok(['data' => [], 'attente' => 0, 'lie' => false]);
        }

        $consultations = Consultation::query()
            ->with(['vente:id,reference_vente,created_at', 'docteurInfo:id,nom,prenom', 'ordres.lignes.service'])
            ->whereBetween('created_at', [$debut, $fin])
            ->unless($voitTout, fn ($q) => $q->where('docteur', (string) $request->user()->employee_id))
            ->orderBy('created_at')
            ->get();

        $rows = $consultations
            ->when($validated['statut'] ?? null, fn ($collection, string $statut) => $collection->where('statut', $statut))
            ->map(fn (Consultation $c): array => [
                'id' => $c->id,
                'patient' => $c->nom_patient,
                'sexe' => Consultation::sexeLibelle($c->sex_patient),
                'age' => $c->ageLibelle(),
                'type_docteur' => $c->type_docteur,
                'docteur' => $c->docteurNom(),
                'reference' => $c->vente?->reference_vente,
                'statut' => $c->statut,
                'created_at' => $c->created_at,
                'vu_at' => $c->vu_at,
                'peut_marquer' => $c->statut === Consultation::EN_ATTENTE,
                'peut_envoyer_en_caisse' => $c->type_docteur === Docteurs::DENTISTE,
                'ordres' => $c->ordres->where('statut', '!=', OrdreSoins::ANNULE)->map(fn (OrdreSoins $ordre): array => [
                    'id' => $ordre->id,
                    'statut' => $ordre->statut,
                    'total' => $ordre->total(),
                    'actes' => $ordre->lignes->map(fn ($ligne): string => ($ligne->qte > 1 ? $ligne->qte.' × ' : '').$ligne->service?->nom_service)->implode(', '),
                ])->values(),
            ])
            ->values();

        return $this->ok([
            'data' => $rows,
            'attente' => $consultations->where('statut', Consultation::EN_ATTENTE)->count(),
            'lie' => true,
        ]);
    }

    /** Le docteur a reçu le patient. */
    public function vu(Request $request, Consultation $consultation): JsonResponse
    {
        abort_unless($this->voitTout($request) || (string) $consultation->docteur === (string) $request->user()->employee_id, 403);

        if ($consultation->statut === Consultation::VU) {
            return $this->echec('Ce patient est déjà marqué comme vu.');
        }

        $consultation->update(['statut' => Consultation::VU, 'vu_at' => now()]);

        return $this->ok(['message' => 'Patient marqué comme vu.']);
    }

    private function voitTout(Request $request): bool
    {
        return $request->user()->hasRole(RoleName::Administrateur);
    }
}

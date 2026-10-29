<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\OrdreSoins;
use App\Models\Pharma\Panier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Soins envoyés en caisse par les dentistes : la caissière les ajoute au panier puis encaisse le patient.
 */
class OrdreCaisseController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        $enPanier = Panier::query()->whereNotNull('ordre_soins_id')->pluck('user_id', 'ordre_soins_id');

        $rows = OrdreSoins::query()
            ->with(['consultation.docteurInfo', 'lignes.service'])
            ->where('statut', OrdreSoins::EN_ATTENTE)
            ->orderBy('created_at')
            ->get()
            ->map(fn (OrdreSoins $ordre): array => [
                'id' => $ordre->id,
                'patient' => $ordre->consultation->nom_patient,
                'docteur' => $ordre->consultation->docteurNom(),
                'note' => $ordre->note,
                'lignes' => $ordre->lignes->map(fn ($ligne): array => ['nom' => $ligne->service?->nom_service, 'qte' => $ligne->qte, 'prix' => $ligne->service?->prix_service])->values(),
                'total' => $ordre->total(),
                'created_at' => $ordre->created_at,
                'dans_mon_panier' => ($enPanier[$ordre->id] ?? null) === auth()->id(),
                'dans_un_autre_panier' => $enPanier->has($ordre->id) && $enPanier[$ordre->id] !== auth()->id(),
            ]);

        return $this->ok(['data' => $rows]);
    }

    /** Ajoute les soins au panier de la caisse, aux prix actuels des services. */
    public function ajouterAuPanier(OrdreSoins $ordreSoins): JsonResponse
    {
        return DB::transaction(function () use ($ordreSoins): JsonResponse {
            $ordre = OrdreSoins::query()->lockForUpdate()->with(['consultation', 'lignes.service'])->findOrFail($ordreSoins->id);

            if ($ordre->statut !== OrdreSoins::EN_ATTENTE) {
                return $this->echec('Ces soins sont déjà réglés ou annulés.');
            }
            if (Panier::query()->where('ordre_soins_id', $ordre->id)->exists()) {
                return $this->echec('Ces soins sont déjà dans un panier.');
            }
            if ($ordre->lignes->contains(fn ($ligne): bool => ! $ligne->service?->etat)) {
                return $this->echec("Un des actes prescrits n'est plus disponible : demandez au dentiste de refaire l'envoi.");
            }

            foreach ($ordre->lignes as $ligne) {
                $prix = (string) $ligne->service->prix_service;

                Panier::create([
                    'type' => 'Service',
                    'service_id' => $ligne->service_id,
                    'ordre_soins_id' => $ordre->id,
                    'nom_patient' => $ordre->consultation->nom_patient,
                    'p_u_brut' => $prix,
                    'qte' => $ligne->qte,
                    'montant_brut' => bcmul($prix, (string) $ligne->qte, 2),
                    'user_id' => auth()->id(),
                ]);
            }

            return $this->ok(['message' => 'Soins ajoutés au panier.', 'patient' => $ordre->consultation->nom_patient], 201);
        });
    }
}

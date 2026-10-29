<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Enums\RoleName;
use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\CaisseSession;
use App\Models\Pharma\Consultation;
use App\Models\Pharma\OrdreSoins;
use App\Models\Pharma\Panier;
use App\Models\Pharma\Vente;
use App\Models\Pharma\VenteDetail;
use App\Services\Pharma\Montant;
use App\Services\Pharma\RecuVente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VenteController extends Controller
{
    use RepondEnJson;

    /**
     * Encaissement atomique :
     * référence unique V+YmdHis, contrôle payé ≥ brut, monnaie = payé − brut,
     * copie des lignes + consultations, vidage du panier.
     * Aucun mouvement de stock ici : la réservation a été faite à l'ajout au panier.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client' => ['required', 'string', 'max:255'],
            'montant_paye' => ['required', 'numeric', 'min:0'],
        ]);

        return DB::transaction(function () use ($validated): JsonResponse {
            // La caisse doit être ouverte pour encaisser
            $session = CaisseSession::query()
                ->where('user_id', auth()->id())
                ->where('statut', CaisseSession::OUVERTE)
                ->orderByDesc('id')
                ->first();
            if (! $session) {
                return $this->echec("La caisse doit être ouverte avant d'encaisser.", 403);
            }

            $lignes = Panier::query()->where('user_id', auth()->id())->orderBy('id')->lockForUpdate()->get();
            if ($lignes->isEmpty()) {
                return $this->echec('Le panier est vide.');
            }

            $totalBrut = '0';
            foreach ($lignes as $ligne) {
                $totalBrut = bcadd($totalBrut, (string) $ligne->montant_brut, 2);
            }

            $paye = number_format((float) $validated['montant_paye'], 2, '.', '');
            if (bccomp($paye, $totalBrut, 2) < 0) {
                return response()->json(['message' => 'Montant insuffisant pour couvrir le total.', 'code' => 'insuffisant'], 422);
            }

            $monnaie = bcsub($paye, $totalBrut, 2);

            // Référence unique avec reprise en cas de collision (2 ventes dans la même seconde)
            $tentatives = 0;
            do {
                $reference = 'V'.now()->format('YmdHis').($tentatives > 0 ? chr(64 + $tentatives) : '');
                $existe = Vente::query()->where('reference_vente', $reference)->exists();
                $tentatives++;
            } while ($existe && $tentatives <= 26);

            if ($existe) {
                return $this->echec('Conflit de référence, veuillez réessayer.', 409);
            }

            $vente = Vente::create([
                'reference_vente' => $reference,
                'user_id' => auth()->id(),
                'caisse_session_id' => $session->id,
                'client' => trim($validated['client']),
                'montant_brut' => $totalBrut,
                'montant_paye' => $paye,
                'monnaie' => $monnaie,
                'mode_paiement' => 'cash',
            ]);

            $resteAImputer = $paye;
            foreach ($lignes as $i => $ligne) {
                $estDerniere = ($i === $lignes->count() - 1);

                // Répartition proportionnelle du montant payé ; la dernière ligne absorbe l'arrondi
                $partPayee = $estDerniere
                    ? $resteAImputer
                    : bcmul((string) $ligne->montant_brut, bcdiv($paye, $totalBrut, 6), 2);
                $partPayee = min($partPayee, (string) $ligne->montant_brut);
                $resteAImputer = bcsub($resteAImputer, $partPayee, 2);

                VenteDetail::create([
                    'vente_id' => $vente->id,
                    'type' => $ligne->type,
                    'consultation_id' => $ligne->consultation_id,
                    'article_id' => $ligne->article_id,
                    'entree_detail_id' => $ligne->entree_detail_id,
                    'service_id' => $ligne->service_id,
                    'analyse_id' => $ligne->analyse_id,
                    'kit_id' => $ligne->kit_id,
                    'p_u_brut' => $ligne->p_u_brut,
                    'qte' => $ligne->qte,
                    'montant_brut' => $ligne->montant_brut,
                    'montant_paye' => $partPayee,
                ]);

                // Les consultations sont copiées avec leur contexte patient/docteur
                if ($ligne->consultation_id) {
                    Consultation::create([
                        'vente_id' => $vente->id,
                        'nom_patient' => $ligne->nom_patient,
                        'sex_patient' => (bool) $ligne->sex_patient,
                        'age_patient' => $ligne->age_patient,
                        'unite_age' => (bool) $ligne->unite_age,
                        'type_docteur' => $ligne->type_docteur,
                        'docteur' => $ligne->docteur,
                    ]);
                }
            }

            // Les soins prescrits par un dentiste qui figuraient dans le panier sont maintenant réglés.
            OrdreSoins::query()
                ->whereIn('id', $lignes->pluck('ordre_soins_id')->filter()->unique())
                ->where('statut', OrdreSoins::EN_ATTENTE)
                ->update(['statut' => OrdreSoins::PAYE, 'vente_id' => $vente->id, 'paye_at' => now()]);

            Panier::query()->where('user_id', auth()->id())->delete();

            return $this->ok([
                'message' => 'Vente validée.',
                'data' => [
                    'id' => $vente->id,
                    'reference' => $vente->reference_vente,
                    'total' => (float) $totalBrut,
                    'paye' => (float) $paye,
                    'monnaie' => (float) $monnaie,
                    'monnaie_arrondie' => Montant::arrondirCentaine((float) $monnaie),
                    'recu_url' => route('pharma.ventes.recu', $vente, absolute: false),
                ],
            ], 201);
        });
    }

    /** Reçu PDF thermique (~80 mm) : un reçu par type présent dans la vente. */
    public function recu(Vente $vente, RecuVente $recu): Response
    {
        abort_unless(Gate::any(['pharma.caisse', 'pharma.historique']), 403);
        self::authorizeAccesVente($vente);

        return $recu->pdf($vente)->stream("recu_{$vente->reference_vente}.pdf");
    }

    /**
     * Contrôle d'accès à une vente : les administrateurs consultent toutes les ventes,
     * les autres comptes uniquement les leurs.
     */
    public static function authorizeAccesVente(Vente $vente): void
    {
        $user = auth()->user();

        abort_unless($user->hasRole(RoleName::Administrateur) || (int) $vente->user_id === (int) $user->id, 403);
    }
}

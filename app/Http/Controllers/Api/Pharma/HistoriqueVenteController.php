<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Enums\RoleName;
use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Consultation;
use App\Models\Pharma\Vente;
use App\Models\Pharma\VenteDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Historique des ventes, filtré par dates, avec statistiques par nature de ligne.
 */
class HistoriqueVenteController extends Controller
{
    use RepondEnJson;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
        ]);

        $user = $request->user();
        $debut = Carbon::parse($validated['date_debut'])->startOfDay();
        $fin = Carbon::parse($validated['date_fin'])->endOfDay();

        $ventes = Vente::query()
            ->with('user:id,name')
            ->whereBetween('created_at', [$debut, $fin])
            // Un compte non administrateur ne voit que ses propres ventes
            ->unless($user->hasRole(RoleName::Administrateur), fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('id')
            ->get();

        $details = VenteDetail::query()->whereIn('vente_id', $ventes->pluck('id'))->get();

        // Statistiques par nature : nombre de lignes vs somme des quantités
        $totalLignes = max(1, $details->count());
        $stats = collect(['Consultation', 'Service', 'Article', 'Analyse', 'Kit'])
            ->map(function (string $nature) use ($details, $totalLignes): array {
                $lignes = $details->where('type', $nature);

                return [
                    'nature' => $nature,
                    'nb_lignes' => $lignes->count(),
                    'pct_lignes' => round($lignes->count() * 100 / $totalLignes),
                    'somme_qte' => (int) $lignes->sum('qte'),
                ];
            })
            ->filter(fn (array $s): bool => $s['nb_lignes'] > 0)
            ->values();

        $rows = $ventes->map(fn (Vente $vente): array => [
            'id' => $vente->id,
            'reference' => $vente->reference_vente,
            'client' => $vente->client,
            'caissier' => $vente->user?->name ?? '—',
            'nb_lignes' => $details->where('vente_id', $vente->id)->count(),
            'montant_brut' => $vente->montant_brut,
            'montant_paye' => $vente->montant_paye,
            'monnaie' => $vente->monnaie,
            'created_at' => $vente->created_at,
        ]);

        return $this->ok([
            'data' => $rows,
            'stats' => $stats,
            'totaux' => [
                'nb_ventes' => $ventes->count(),
                'montant_brut' => (float) $ventes->sum('montant_brut'),
                'montant_paye' => (float) $ventes->sum('montant_paye'),
                'monnaie' => (float) $ventes->sum('monnaie'),
            ],
        ]);
    }

    /** Détail d'une vente pour la fenêtre de consultation. */
    public function show(Vente $vente): JsonResponse
    {
        VenteController::authorizeAccesVente($vente);
        $vente->load('user:id,name');

        $lignes = VenteDetail::query()
            ->with(['article', 'service', 'kit'])
            ->where('vente_id', $vente->id)
            ->orderBy('id')
            ->get()
            ->map(fn (VenteDetail $ligne): array => [
                'type' => $ligne->type,
                'libelle' => $ligne->libelle(),
                'p_u_brut' => $ligne->p_u_brut,
                'qte' => (int) $ligne->qte,
                'montant_brut' => $ligne->montant_brut,
                'paye' => $ligne->montant_paye,
            ]);

        $consultations = $vente->consultations()->with('docteurInfo')->get()->map(fn (Consultation $c): array => [
            'patient' => $c->nom_patient,
            'sexe' => Consultation::sexeLibelle($c->sex_patient),
            'age' => $c->ageLibelle(),
            'type_docteur' => $c->type_docteur,
            'docteur' => $c->docteurNom(),
        ]);

        return $this->ok([
            'vente' => [
                'id' => $vente->id,
                'reference' => $vente->reference_vente,
                'client' => $vente->client,
                'caissier' => $vente->user?->name ?? '—',
                'montant_brut' => $vente->montant_brut,
                'montant_paye' => $vente->montant_paye,
                'monnaie' => $vente->monnaie,
                'mode_paiement' => $vente->mode_paiement,
                'created_at' => $vente->created_at,
            ],
            'lignes' => $lignes,
            'consultations' => $consultations,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\CaisseSession;
use App\Models\Pharma\Panier;
use App\Services\Pharma\VenteTotauxUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Session de caisse : fond de caisse à l'ouverture, comptage et écart à la clôture.
 */
class CaisseSessionController extends Controller
{
    use RepondEnJson;

    /** Session actuellement ouverte par l'utilisateur connecté (ou null). */
    public function show(): JsonResponse
    {
        $session = $this->sessionOuverte();

        if (! $session) {
            return $this->ok(['data' => null]);
        }

        $stats = $this->totauxSession($session);

        return $this->ok(['data' => [
            'id' => $session->id,
            'fond_de_caisse' => (float) $session->fond_de_caisse,
            'ouverture_at' => $session->ouverture_at,
            'nb_ventes' => $stats['nb'],
            'total_ventes' => $stats['total_ventes'],
            'total_encaisse' => $stats['total_encaisse'],
            'total_monnaie' => $stats['total_monnaie'],
            'theorique' => $stats['theorique'],
        ]]);
    }

    public function ouvrir(Request $request): JsonResponse
    {
        $validated = $request->validate(['fond_de_caisse' => ['required', 'numeric', 'min:0']]);

        if ($this->sessionOuverte()) {
            return $this->echec('Une caisse est déjà ouverte pour cet utilisateur.', 409);
        }

        $session = CaisseSession::create([
            'user_id' => auth()->id(),
            'statut' => CaisseSession::OUVERTE,
            'fond_de_caisse' => (float) $validated['fond_de_caisse'],
            'ouverture_at' => now(),
        ]);

        return $this->ok(['message' => 'Caisse ouverte.', 'data' => ['id' => $session->id]], 201);
    }

    public function fermer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'montant_reel' => ['required', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string', 'max:255'],
        ]);

        $session = $this->sessionOuverte();
        if (! $session) {
            return $this->echec('Aucune caisse ouverte pour cet utilisateur.', 404);
        }

        DB::transaction(function () use ($session, $validated): void {
            $reel = (float) $validated['montant_reel'];
            $stats = $this->totauxSession($session);

            $session->update([
                'statut' => CaisseSession::FERMEE,
                'fermeture_at' => now(),
                'total_ventes' => $stats['total_ventes'],
                'total_encaisse' => $stats['total_encaisse'],
                'total_monnaie' => $stats['total_monnaie'],
                'theorique' => $stats['theorique'],
                'montant_reel' => $reel,
                'ecart' => round($reel - $stats['theorique'], 2),
                'observations' => $validated['observations'] ?? null,
            ]);

            // Nettoyage du panier résiduel éventuel
            Panier::query()->where('user_id', auth()->id())->delete();
        });

        return $this->ok(['message' => 'Caisse fermée et soldée.']);
    }

    /** Session ouverte du caissier connecté (une seule possible à la fois). */
    private function sessionOuverte(): ?CaisseSession
    {
        return CaisseSession::query()
            ->where('user_id', auth()->id())
            ->where('statut', CaisseSession::OUVERTE)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Totaux cumulés des ventes de la session.
     *
     * @return array{nb: int, total_ventes: float, total_encaisse: float, total_monnaie: float, theorique: float}
     */
    private function totauxSession(CaisseSession $session): array
    {
        $totaux = VenteTotauxUtil::pourSession($session->id);

        return [
            'nb' => $totaux['nb'],
            'total_ventes' => round($totaux['total_ventes'], 2),
            'total_encaisse' => round($totaux['total_encaisse'], 2),
            'total_monnaie' => round($totaux['total_monnaie'], 2),
            'theorique' => round((float) $session->fond_de_caisse + $totaux['total_ventes'], 2),
        ];
    }
}

<?php

namespace App\Services\Pharma;

use Illuminate\Support\Facades\DB;

/**
 * Agrégats des ventes — utilisés pour la clôture d'une session de caisse.
 */
class VenteTotauxUtil
{
    /**
     * Totaux cumulés de toutes les ventes rattachées à une session de caisse.
     *
     * @return array{nb: int, total_ventes: float, total_encaisse: float, total_monnaie: float}
     */
    public static function pourSession(int $caisseSessionId): array
    {
        $row = DB::table('pharma_ventes')
            ->where('caisse_session_id', $caisseSessionId)
            ->selectRaw(
                'COUNT(*) as nb,
                 COALESCE(SUM(montant_brut), 0) as total_ventes,
                 COALESCE(SUM(montant_paye), 0) as total_encaisse,
                 COALESCE(SUM(monnaie), 0) as total_monnaie'
            )
            ->first();

        return [
            'nb' => (int) $row->nb,
            'total_ventes' => (float) $row->total_ventes,
            'total_encaisse' => (float) $row->total_encaisse,
            'total_monnaie' => (float) $row->total_monnaie,
        ];
    }
}

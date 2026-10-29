<?php

namespace App\Services\Pharma;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consommation Moyenne Mensuelle (CMM) calculée à partir des lignes de vente.
 * CMM = quantité vendue × 30 / nombre de jours de la période filtrée.
 */
class ConsommationService
{
    /** Nombre de jours inclus dans l'intervalle [debut, fin] (minimum 1). */
    public static function nbJours(Carbon $debut, Carbon $fin): int
    {
        return max(1, (int) $debut->startOfDay()->diffInDays($fin->startOfDay()) + 1);
    }

    /** Quantités vendues par article sur l'intervalle, indexées par article_id. */
    public static function statsParArticle(Carbon $debut, Carbon $fin): array
    {
        $rows = DB::table('pharma_vente_details as vd')
            ->join('pharma_ventes as v', 'v.id', '=', 'vd.vente_id')
            ->whereNotNull('vd.article_id')
            ->whereBetween('v.created_at', [$debut->startOfDay(), $fin->endOfDay()])
            ->groupBy('vd.article_id')
            ->selectRaw('vd.article_id, SUM(vd.qte) as qte')
            ->get();

        return $rows->keyBy('article_id')->map(fn ($r) => (int) $r->qte)->all();
    }

    /** CMM d'un article sur la période : qte × 30 / nbJours (0 si aucune vente). */
    public static function cmmArticle(?int $qteVendue, int $nbJours): float
    {
        if (! $qteVendue || $qteVendue <= 0) {
            return 0.0;
        }

        return round($qteVendue * 30 / $nbJours, 2);
    }

    /** Proposition de commande basée CMM : CMM × mois de couverture − stock. */
    public static function proposition(float $cmm, int $moisCouverture, int $stock): int
    {
        return max(0, (int) ceil($cmm * $moisCouverture) - $stock);
    }
}

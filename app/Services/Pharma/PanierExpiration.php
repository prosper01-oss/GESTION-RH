<?php

namespace App\Services\Pharma;

use App\Models\Pharma\Article;
use App\Models\Pharma\EntreeDetail;
use App\Models\Pharma\Panier;
use App\Models\Pharma\StockMouvement;
use Illuminate\Support\Facades\DB;

/**
 * Expiration des paniers non finalisés.
 * Restitue le stock des lignes « Article » restées en panier au-delà du délai configuré
 * (config pharma.panier_expiration_minutes, 60 min par défaut).
 */
class PanierExpiration
{
    public static function minutes(): int
    {
        return (int) config('pharma.panier_expiration_minutes');
    }

    /**
     * Purge les lignes de panier (stock réservé) plus anciennes que le délai.
     *
     * @param  int|null  $userId  null = purger tous les utilisateurs
     */
    public static function purger(?int $userId = null, ?int $minutes = null): int
    {
        $minutes ??= self::minutes();

        return DB::transaction(function () use ($userId, $minutes) {
            $lignes = Panier::query()
                ->whereNotNull('entree_detail_id')
                ->whereNotNull('article_id')
                ->where('created_at', '<', now()->subMinutes($minutes))
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->lockForUpdate()
                ->get();

            foreach ($lignes as $ligne) {
                $avant = (int) DB::table('pharma_articles')->where('id', $ligne->article_id)->value('stock');

                EntreeDetail::query()->where('id', $ligne->entree_detail_id)->update([
                    'stock_restant_lot' => DB::raw('stock_restant_lot + '.(int) $ligne->qte),
                    'stock_dispo' => DB::raw('stock_dispo + '.(int) $ligne->qte),
                    'isVendus' => false,
                ]);

                Article::query()->where('id', $ligne->article_id)->increment('stock', (int) $ligne->qte);

                StockMouvement::ecrire(
                    $ligne->article_id,
                    StockMouvement::TYPE_PANIER_RETRAIT,
                    (int) $ligne->qte,
                    $avant,
                    $avant + (int) $ligne->qte,
                    $ligne->entree_detail_id,
                    null,
                    'Panier expiré restitué automatiquement',
                );

                $ligne->delete();
            }

            return $lignes->count();
        });
    }
}

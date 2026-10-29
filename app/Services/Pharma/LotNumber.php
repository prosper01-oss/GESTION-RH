<?php

namespace App\Services\Pharma;

use App\Models\Pharma\EntreeDetail;

class LotNumber
{
    /**
     * Convertit un entier en séquence de lettres bijective base 26 :
     * 0 => A, 1 => B ... 25 => Z, 26 => AA, 27 => AB ...
     */
    public static function lettres(int $n): string
    {
        $letters = '';
        $n = max(0, $n);
        while (true) {
            $letters = chr(65 + ($n % 26)).$letters;
            $n = intdiv($n, 26) - 1;
            if ($n < 0) {
                break;
            }
        }

        return $letters;
    }

    /**
     * Génère un numéro de lot : LOT-{lettres}-{N° article sur 4}-{jj/mm/aa}.
     * Le compteur inclut les lignes supprimées logiquement (stabilité).
     */
    public static function generer(int $articleId): string
    {
        $count = EntreeDetail::query()->where('article_id', $articleId)->count();
        $code = str_pad((string) $articleId, 4, '0', STR_PAD_LEFT);

        return 'LOT-'.self::lettres($count)."-{$code}-".date('d/m/y');
    }
}

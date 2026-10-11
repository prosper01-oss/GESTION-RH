<?php

namespace App\Models;

use App\Enums\RequestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['doc_type', 'year', 'last_number'])]
class DocumentSequence extends Model
{
    public $timestamps = false;

    /**
     * Référence suivante, par exemple OMIT-CG-2026-00125. Le compteur est verrouillé
     * dans une transaction pour qu'aucune référence ne soit attribuée deux fois.
     */
    public static function nextReference(RequestType $type, int $year): string
    {
        return DB::transaction(function () use ($type, $year): string {
            $prefix = $type->referencePrefix();

            self::query()->firstOrCreate(['doc_type' => $prefix, 'year' => $year], ['last_number' => 0]);
            $sequence = self::query()->where(['doc_type' => $prefix, 'year' => $year])->lockForUpdate()->firstOrFail();
            $sequence->increment('last_number');

            return sprintf('OMIT-%s-%d-%05d', $prefix, $year, $sequence->last_number);
        });
    }
}

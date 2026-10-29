<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['pourcentage'])]
class Pourcentage extends Model
{
    protected $table = 'pharma_pourcentages';

    public static function taux(): int
    {
        return (int) (static::query()->value('pourcentage') ?? 20);
    }
}

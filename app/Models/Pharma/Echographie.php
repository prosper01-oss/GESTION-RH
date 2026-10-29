<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nom', 'prix', 'etat'])]
class Echographie extends Model
{
    protected $table = 'pharma_echographies';

    protected function casts(): array
    {
        return [
            'etat' => 'boolean',
            'prix' => 'float',
        ];
    }
}

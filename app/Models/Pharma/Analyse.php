<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nom', 'prix', 'etat'])]
class Analyse extends Model
{
    protected $table = 'pharma_analyses';

    protected function casts(): array
    {
        return [
            'etat' => 'boolean',
            'prix' => 'float',
        ];
    }
}

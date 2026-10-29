<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Lot C — Fournisseur (référentiel achats).
 */
#[Fillable(['nom', 'telephone', 'email', 'adresse', 'actif'])]
class Fournisseur extends Model
{
    protected $table = 'pharma_fournisseurs';

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function entrees()
    {
        return $this->hasMany(EntreeIndex::class, 'fournisseur_id');
    }
}

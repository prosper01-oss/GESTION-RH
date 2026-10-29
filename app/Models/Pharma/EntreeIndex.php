<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ref_entree', 'motif', 'fournisseur_id', 'nb_article', 'montant_total', 'etat'])]
class EntreeIndex extends Model
{
    protected $table = 'pharma_entree_indices';

    protected function casts(): array
    {
        return [
            'etat' => 'boolean',
            'montant_total' => 'float',
        ];
    }

    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    public function details()
    {
        return $this->hasMany(EntreeDetail::class, 'entree_indices_id');
    }

    public function detailsActifs()
    {
        return $this->details()->where('etat', 1);
    }
}

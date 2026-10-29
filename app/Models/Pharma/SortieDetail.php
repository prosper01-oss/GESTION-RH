<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'id_entree_detail',
    'sortie_indices_id',
    'qte_initial',
    'qte_sortie',
    'stock_restant_lot',
    'stock_dispo',
    'montant_perte',
    'etat',
])]
class SortieDetail extends Model
{
    protected $table = 'pharma_sortie_details';

    protected function casts(): array
    {
        return [
            'etat' => 'boolean',
            'montant_perte' => 'float',
        ];
    }

    public function lot()
    {
        return $this->belongsTo(EntreeDetail::class, 'id_entree_detail');
    }

    public function sortieIndex()
    {
        return $this->belongsTo(SortieIndex::class, 'sortie_indices_id');
    }
}

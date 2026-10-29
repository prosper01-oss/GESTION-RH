<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['categorie_id', 'nom_service', 'prix_service', 'isConsult', 'acte_dentaire', 'etat'])]
class Service extends Model
{
    protected $table = 'pharma_services';

    protected function casts(): array
    {
        return [
            'etat' => 'boolean',
            'acte_dentaire' => 'boolean',
            'prix_service' => 'float',
        ];
    }

    public function categorie()
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }
}

<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'entree_detail_id',
    'type',
    'article_id',
    'analyse_id',
    'service_id',
    'consultation_id',
    'kit_id',
    'ordre_soins_id',
    'nom_patient',
    'sex_patient',
    'age_patient',
    'unite_age',
    'type_docteur',
    'docteur',
    'p_u_brut',
    'qte',
    'montant_brut',
    'user_id',
])]
class Panier extends Model
{
    protected $table = 'pharma_paniers';

    protected function casts(): array
    {
        return [
            'p_u_brut' => 'float',
            'montant_brut' => 'float',
            'sex_patient' => 'boolean',
            'unite_age' => 'boolean',
        ];
    }

    public const TYPES = ['Consultation', 'Service', 'Article', 'Analyse', 'Kit'];
}

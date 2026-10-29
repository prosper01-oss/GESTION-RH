<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['ref_sortie', 'motif', 'nb_article', 'montant_total', 'etat'])]
class SortieIndex extends Model
{
    protected $table = 'pharma_sortie_indices';

    public const MOTIFS = ['ajustement', 'perte', 'casse', 'perime'];

    protected function casts(): array
    {
        return [
            'etat' => 'boolean',
            'montant_total' => 'float',
        ];
    }

    public function details()
    {
        return $this->hasMany(SortieDetail::class, 'sortie_indices_id');
    }

    public function detailsActifs()
    {
        return $this->details()->where('etat', 1);
    }

    public static function motifsLibelles(): array
    {
        return [
            'ajustement' => 'Ajustement',
            'perte' => 'Perte',
            'casse' => 'Casse',
            'perime' => 'Périmé',
        ];
    }
}

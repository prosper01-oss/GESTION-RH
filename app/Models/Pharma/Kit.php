<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nom_kit', 'prix_kit', 'etat'])]
class Kit extends Model
{
    protected $table = 'pharma_kits';

    protected function casts(): array
    {
        return [
            'etat' => 'boolean',
            'prix_kit' => 'float',
        ];
    }

    public function details()
    {
        return $this->hasMany(DetailKit::class, 'id_kit');
    }
}

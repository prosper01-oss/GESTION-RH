<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nom_cat', 'etat'])]
class Categorie extends Model
{
    protected $table = 'pharma_categories';

    protected function casts(): array
    {
        return ['etat' => 'boolean'];
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}

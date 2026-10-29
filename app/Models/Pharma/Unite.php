<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nomUnite', 'nomComplet', 'supun'])]
class Unite extends Model
{
    protected $table = 'pharma_unites';

    protected function casts(): array
    {
        return ['supun' => 'boolean'];
    }

    public function articles()
    {
        return $this->hasMany(Article::class, 'unite');
    }
}

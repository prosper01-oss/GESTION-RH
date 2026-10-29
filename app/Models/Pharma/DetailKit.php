<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id_kit', 'id_article', 'qte_kit', 'etat'])]
class DetailKit extends Model
{
    protected $table = 'pharma_detail_kits';

    protected function casts(): array
    {
        return ['etat' => 'boolean'];
    }

    public function kit()
    {
        return $this->belongsTo(Kit::class, 'id_kit');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'id_article');
    }
}

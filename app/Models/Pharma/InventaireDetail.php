<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Lot B — Ligne de comptage d'inventaire.
 * écart = compte - theorique (journal des écarts d'inventaire).
 */
#[Fillable(['inventaire_id', 'article_id', 'theorique', 'compte', 'ecart', 'entree_detail_id'])]
class InventaireDetail extends Model
{
    protected $table = 'pharma_inventaire_details';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'theorique' => 'integer',
            'compte' => 'integer',
            'ecart' => 'integer',
        ];
    }

    public function inventaire()
    {
        return $this->belongsTo(Inventaire::class, 'inventaire_id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id');
    }
}

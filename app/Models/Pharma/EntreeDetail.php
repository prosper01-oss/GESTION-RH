<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'entree_indices_id',
    'article_id',
    'lot',
    'source',
    'qte_initial',
    'qte_entree',
    'stock_restant_lot',
    'stock_dispo',
    'prix_achat_boite',
    'prix_unitaire',
    'montant_entree',
    'montant_gain_brut',
    'nb_lot_dispo',
    'date_peremption',
    'isValide',
    'isVendus',
    'etat',
])]
class EntreeDetail extends Model
{
    protected $table = 'pharma_entree_details';

    /** Seuils de péremption (jours restants avant la date de péremption). */
    public const DELAI_URGENT_JOURS = 30;

    public const DELAI_PROCHE_JOURS = 90;

    /** Origine du lot. */
    public const SOURCE_MANUEL = 'manuel';

    public const SOURCE_IMPORT = 'import';

    protected function casts(): array
    {
        return [
            'prix_achat_boite' => 'float',
            'prix_unitaire' => 'float',
            'montant_entree' => 'float',
            'montant_gain_brut' => 'float',
            'date_peremption' => 'date:Y-m-d',
            'isValide' => 'boolean',
            'isVendus' => 'boolean',
            'etat' => 'boolean',
        ];
    }

    public function entreeIndex()
    {
        return $this->belongsTo(EntreeIndex::class, 'entree_indices_id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function estPerime(): bool
    {
        return $this->date_peremption !== null && $this->date_peremption->isPast();
    }

    /** RG-SEC-01 : un lot importé est en lecture seule. */
    public function estImporte(): bool
    {
        return $this->source === self::SOURCE_IMPORT;
    }

    /**
     * Statut de péremption relatif à la date du jour :
     * perime | urgent (≤ 30 j) | proche (≤ 90 j) | ok | null (pas de date).
     */
    public function statutPeremption(): ?string
    {
        if ($this->date_peremption === null) {
            return null;
        }

        $joursRestants = (int) now()->startOfDay()
            ->diffInDays($this->date_peremption->startOfDay(), false);

        return match (true) {
            $joursRestants < 0 => 'perime',
            $joursRestants <= self::DELAI_URGENT_JOURS => 'urgent',
            $joursRestants <= self::DELAI_PROCHE_JOURS => 'proche',
            default => 'ok',
        };
    }
}

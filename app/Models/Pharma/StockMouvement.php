<?php

namespace App\Models\Pharma;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal des mouvements de stock (Lot A — audit).
 * Toute écriture de stock est tracée : entrées, sorties, paniers, inventaires.
 */
#[Fillable([
    'article_id',
    'entree_detail_id',
    'type',
    'qte',
    'stock_avant',
    'stock_apres',
    'reference',
    'libelle',
    'user_id',
])]
class StockMouvement extends Model
{
    protected $table = 'pharma_stock_mouvements';

    public const TYPE_ENTREE = 'entree';

    public const TYPE_ENTREE_ANNULEE = 'entree_annulation';

    public const TYPE_SORTIE = 'sortie';

    public const TYPE_SORTIE_RESTITUEE = 'sortie_restauration';

    public const TYPE_PANIER = 'panier';

    public const TYPE_PANIER_RETRAIT = 'panier_retrait';

    public const TYPE_INVENTAIRE = 'inventaire';

    public const TYPE_INVENTAIRE_ANNULE = 'inventaire_annulation';

    protected function casts(): array
    {
        return [
            'qte' => 'integer',
            'stock_avant' => 'integer',
            'stock_apres' => 'integer',
        ];
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function entreeDetail()
    {
        return $this->belongsTo(EntreeDetail::class, 'entree_detail_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function typesLibelles(): array
    {
        return [
            self::TYPE_ENTREE => 'Entrée',
            self::TYPE_ENTREE_ANNULEE => 'Entrée annulée',
            self::TYPE_SORTIE => 'Sortie / perte',
            self::TYPE_SORTIE_RESTITUEE => 'Sortie restituée',
            self::TYPE_PANIER => 'Réservation caisse',
            self::TYPE_PANIER_RETRAIT => 'Retrait panier',
            self::TYPE_INVENTAIRE => 'Inventaire',
            self::TYPE_INVENTAIRE_ANNULE => 'Inventaire annulé',
        ];
    }

    public static function typeLibelle(string $type): string
    {
        return self::typesLibelles()[$type] ?? $type;
    }

    /**
     * Trace un mouvement de stock.
     *
     * @param  int  $qte  quantité signée (+ entrée, − sortie)
     */
    public static function ecrire(
        int $articleId,
        string $type,
        int $qte,
        ?int $stockAvant = null,
        ?int $stockApres = null,
        ?int $lotId = null,
        ?string $reference = null,
        ?string $libelle = null,
    ): self {
        return self::create([
            'article_id' => $articleId,
            'entree_detail_id' => $lotId,
            'type' => $type,
            'qte' => $qte,
            'stock_avant' => $stockAvant,
            'stock_apres' => $stockApres,
            'reference' => $reference,
            'libelle' => $libelle,
            'user_id' => auth()->id(),
        ]);
    }
}

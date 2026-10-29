<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['designation', 'presentation', 'unite', 'stock', 'seuil', 'qte_max', 'statut', 'etat'])]
class Article extends Model
{
    protected $table = 'pharma_articles';

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'seuil' => 'integer',
            'qte_max' => 'integer',
            'statut' => 'integer',
            'etat' => 'integer',
        ];
    }

    public function uniteRelation()
    {
        return $this->belongsTo(Unite::class, 'unite');
    }

    public function lots()
    {
        return $this->hasMany(EntreeDetail::class, 'article_id');
    }

    public function lotsDisponibles()
    {
        return $this->lots()
            ->where('isValide', 1)
            ->where('etat', 1)
            ->where('stock_restant_lot', '>', 0);
    }

    /** Nombre de boîtes équivalentes (arrondi 2 décimales). */
    public function stockBoites(): float
    {
        return $this->presentation > 0 ? round($this->stock / $this->presentation, 2) : 0.0;
    }

    /** Coût unitaire d'achat d'un lot : prix de la boîte / nombre d'unités. */
    public static function coutUnitaireLot(EntreeDetail $lot): float
    {
        $presentation = (int) $lot->article_presentation ?? 0;

        return $presentation > 0 ? (float) $lot->prix_achat_boite / $presentation : (float) $lot->prix_unitaire;
    }

    /** Lots validés restants (tous), et leur sous-ensemble non périmé. */
    public function lotsValides(bool $sansPerimes = false)
    {
        return $this->lotsDisponibles()
            ->when($sansPerimes, fn ($q) => $q->where(function ($q2) {
                $q2->whereNull('date_peremption')
                    ->orWhereDate('date_peremption', '>=', now()->toDateString());
            }));
    }

    /** Coût moyen pondéré (CMUP) des unités restantes, en coût d'achat. Périmés exclus. */
    public function cmup(): float
    {
        [$cout, $qte] = $this->sommesLots(true);

        return $qte > 0 ? round($cout / $qte, 2) : 0.0;
    }

    /** Valeur du stock restant au coût d'achat (vendable, périmés exclus). */
    public function valeurStock(): float
    {
        [$cout] = $this->sommesLots(true);

        return round($cout, 2);
    }

    /** Valeur totale restante au coût d'achat, périmés inclus (perte potentielle). */
    public function valeurStockTotale(): float
    {
        [$cout] = $this->sommesLots(false);

        return round($cout, 2);
    }

    private function sommesLots(bool $sansPerimes): array
    {
        $lots = $this->lotsValides($sansPerimes)
            ->get(['prix_achat_boite', 'prix_unitaire', 'stock_restant_lot'])
            ->map(function (EntreeDetail $lot) {
                $lot->article_presentation = $this->presentation;

                return $lot;
            });

        $cout = $qte = 0;
        foreach ($lots as $lot) {
            $r = (int) $lot->stock_restant_lot;
            $cout += self::coutUnitaireLot($lot) * $r;
            $qte += $r;
        }

        return [$cout, $qte];
    }
}

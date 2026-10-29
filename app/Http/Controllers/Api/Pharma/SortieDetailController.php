<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Article;
use App\Models\Pharma\EntreeDetail;
use App\Models\Pharma\SortieDetail;
use App\Models\Pharma\SortieIndex;
use App\Models\Pharma\StockMouvement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lignes de sortie : sélection d'un lot validé, décrément du stock et valorisation de la perte
 * au coût d'achat unitaire.
 */
class SortieDetailController extends Controller
{
    use RepondEnJson;

    public function index(SortieIndex $sortieIndex): JsonResponse
    {
        abort_unless($sortieIndex->etat, 404);

        $lignes = SortieDetail::query()
            ->with('lot.article.uniteRelation')
            ->where(['etat' => 1, 'sortie_indices_id' => $sortieIndex->id])
            ->orderByDesc('id')
            ->get()
            ->map(function (SortieDetail $ligne): array {
                $lot = $ligne->lot;
                $article = $lot?->article;
                $presentation = max(1, (int) ($article?->presentation ?? 1));

                return [
                    'id' => $ligne->id,
                    'reference' => 'REF-'.str_pad((string) ($article?->id ?? 0), 4, '0', STR_PAD_LEFT),
                    'designation' => $article?->designation ?? 'Article supprimé',
                    'lot' => $lot?->lot ?? '',
                    'qte_sortie_boites' => round($ligne->qte_sortie / $presentation, 2),
                    'qte_sortie_unites' => (int) $ligne->qte_sortie,
                    'stock_restant_lot_boites' => round($ligne->stock_restant_lot / $presentation, 2),
                    'stock_dispo_boites' => round($ligne->stock_dispo / $presentation, 2),
                    'montant_perte' => $ligne->montant_perte,
                    'date_creation' => $ligne->created_at,
                ];
            });

        return $this->ok([
            'index' => ['id' => $sortieIndex->id, 'ref' => $sortieIndex->ref_sortie, 'motif' => $sortieIndex->motif],
            'data' => $lignes,
        ]);
    }

    /** Lots disponibles (validés, restant > 0) d'un article, ordre FEFO. */
    public function lots(Request $request): JsonResponse
    {
        $request->validate(['article_id' => ['required', 'integer']]);

        $lots = EntreeDetail::query()
            ->where([
                ['article_id', $request->integer('article_id')],
                ['isValide', true],
                ['etat', true],
            ])
            ->where('stock_restant_lot', '>', 0)
            ->orderByRaw('date_peremption IS NULL ASC')
            ->orderBy('date_peremption')
            ->orderBy('created_at')
            ->get()
            ->map(fn (EntreeDetail $lot): array => [
                'id' => $lot->id,
                'lot' => $lot->lot,
                'restant' => (int) $lot->stock_restant_lot,
                'date_peremption' => $lot->date_peremption?->format('Y-m-d'),
                'perime' => $lot->estPerime(),
            ]);

        return $this->ok(['data' => $lots]);
    }

    /** Ajout d'une ligne : décrément verrouillé des stocks + valorisation de la perte. */
    public function store(Request $request, SortieIndex $sortieIndex): JsonResponse
    {
        $validated = $request->validate([
            'entree_detail_id' => ['required', 'integer'],
            'qte_sortie' => ['required', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($validated, $sortieIndex): JsonResponse {
            $header = SortieIndex::query()->lockForUpdate()->findOrFail($sortieIndex->id);
            if (((int) $header->etat) !== 1) {
                return $this->echec('Cette sortie est supprimée.');
            }

            $lot = EntreeDetail::query()->lockForUpdate()->findOrFail($validated['entree_detail_id']);
            if (! $lot->isValide || ((int) $lot->etat) !== 1) {
                return $this->echec("Ce lot n'est pas disponible.");
            }
            if (((int) $lot->stock_restant_lot) < $validated['qte_sortie']) {
                return $this->echec("Quantité insuffisante sur ce lot : reste {$lot->stock_restant_lot} unité(s).");
            }

            $dejaAjoute = SortieDetail::query()
                ->where(['sortie_indices_id' => $header->id, 'id_entree_detail' => $lot->id, 'etat' => 1])
                ->exists();
            if ($dejaAjoute) {
                return $this->echec('Ce lot figure déjà dans cette sortie.');
            }

            $article = Article::query()->lockForUpdate()->findOrFail($lot->article_id);
            $presentation = max(1, (int) $article->presentation);
            $qte = (int) $validated['qte_sortie'];

            // Valorisation de la perte au coût d'achat unitaire
            $coutAchatUnitaire = bcdiv((string) $lot->prix_achat_boite, (string) $presentation, 2);
            $montantPerte = bcmul($coutAchatUnitaire, (string) $qte, 2);

            $qteAvant = (int) $lot->stock_restant_lot;
            $stockAvant = (int) $article->stock;

            $ligne = SortieDetail::create([
                'id_entree_detail' => $lot->id,
                'sortie_indices_id' => $header->id,
                'qte_initial' => $qteAvant,
                'qte_sortie' => $qte,
                'stock_restant_lot' => $qteAvant - $qte,
                'stock_dispo' => $stockAvant - $qte,
                'montant_perte' => $montantPerte,
            ]);

            // Les lignes sont verrouillées : les nouvelles valeurs sont calculées ici, sans dépendre du SGBD.
            $lot->update([
                'stock_restant_lot' => max(0, $qteAvant - $qte),
                'stock_dispo' => max(0, (int) $lot->stock_dispo - $qte),
                'isVendus' => $qteAvant - $qte <= 0,
            ]);
            $article->update(['stock' => max(0, $stockAvant - $qte)]);

            $this->rafraichirAggregats($header);

            StockMouvement::ecrire(
                $lot->article_id,
                StockMouvement::TYPE_SORTIE,
                -$qte,
                $stockAvant,
                max(0, $stockAvant - $qte),
                $lot->id,
                $header->ref_sortie,
                "Sortie {$header->ref_sortie} (motif {$header->motif}), lot {$lot->lot}",
            );

            return $this->ok(['message' => 'Ligne de sortie ajoutée.', 'id' => $ligne->id], 201);
        });
    }

    /** Annulation d'une ligne : restitution intégrale des quantités sorties. */
    public function destroy(SortieDetail $sortieDetail): JsonResponse
    {
        return DB::transaction(function () use ($sortieDetail): JsonResponse {
            $ligne = SortieDetail::query()->lockForUpdate()->findOrFail($sortieDetail->id);
            if (((int) $ligne->etat) !== 1) {
                return $this->echec('Ligne déjà supprimée.');
            }

            $lot = EntreeDetail::query()->lockForUpdate()->findOrFail($ligne->id_entree_detail);
            $article = Article::query()->lockForUpdate()->findOrFail($lot->article_id);
            $articleAvant = (int) $article->stock;
            $qte = (int) $ligne->qte_sortie;

            $lot->update([
                'stock_restant_lot' => (int) $lot->stock_restant_lot + $qte,
                'stock_dispo' => (int) $lot->stock_dispo + $qte,
                'isVendus' => false,
            ]);
            $article->update(['stock' => $articleAvant + $qte]);
            $ligne->update(['etat' => 0]);

            $header = SortieIndex::query()->findOrFail($ligne->sortie_indices_id);
            $this->rafraichirAggregats($header);

            StockMouvement::ecrire(
                $lot->article_id,
                StockMouvement::TYPE_SORTIE_RESTITUEE,
                $qte,
                $articleAvant,
                $articleAvant + $qte,
                $lot->id,
                $header->ref_sortie,
                "Restauration ligne sortie {$header->ref_sortie}",
            );

            return $this->ok(['message' => 'Ligne supprimée, stock restauré.']);
        });
    }

    private function rafraichirAggregats(SortieIndex $header): void
    {
        $agg = SortieDetail::query()
            ->where(['sortie_indices_id' => $header->id, 'etat' => 1])
            ->selectRaw('COUNT(*) as nb, COALESCE(SUM(montant_perte),0) as total')
            ->first();

        $header->forceFill([
            'nb_article' => (int) ($agg->nb ?? 0),
            'montant_total' => (float) ($agg->total ?? 0),
        ])->save();
    }
}

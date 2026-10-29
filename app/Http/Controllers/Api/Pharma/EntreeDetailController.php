<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharma\StoreEntreeDetailRequest;
use App\Imports\Pharma\EntreeDetailImport;
use App\Models\Pharma\Article;
use App\Models\Pharma\EntreeDetail;
use App\Models\Pharma\EntreeIndex;
use App\Models\Pharma\Pourcentage;
use App\Models\Pharma\StockMouvement;
use App\Services\Pharma\LotNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Lots d'une entrée de stock : saisie, validation (intégration au stock), annulation, import Excel.
 */
class EntreeDetailController extends Controller
{
    use RepondEnJson;

    public function index(EntreeIndex $entreeIndex): JsonResponse
    {
        abort_unless($entreeIndex->etat, 404);

        $rows = EntreeDetail::query()
            ->with('article.uniteRelation')
            ->where('etat', 1)
            ->where('entree_indices_id', $entreeIndex->id)
            ->orderByDesc('id')
            ->get()
            ->map(function (EntreeDetail $lot): array {
                $article = $lot->article;
                $presentation = max(1, (int) ($article?->presentation ?? 1));
                // Un lot est « utilisé » dès qu'une unité a été consommée (ventes ou sorties) après validation
                $estUtilise = $lot->isValide && ((int) $lot->stock_restant_lot) < ((int) $lot->qte_entree);

                return [
                    'id' => $lot->id,
                    'article_id' => $article?->id,
                    'article' => $article?->designation ?? 'Article supprimé',
                    'presentation' => ($article?->uniteRelation?->nomComplet ?? '').'/'.$presentation,
                    'lot' => $lot->lot,
                    'qte_entree_boites' => round($lot->qte_entree / $presentation, 2),
                    'qte_entree_unites' => (int) $lot->qte_entree,
                    'stock_restant_lot_boites' => round($lot->stock_restant_lot / $presentation, 2),
                    'stock_restant_lot_unites' => (int) $lot->stock_restant_lot,
                    'prix_achat_boite' => $lot->prix_achat_boite,
                    'prix_unitaire' => $lot->prix_unitaire,
                    'montant_entree' => $lot->montant_entree,
                    'date_peremption' => $lot->date_peremption?->format('Y-m-d'),
                    'perime' => $lot->estPerime(),
                    'peremption_statut' => $lot->statutPeremption(),
                    'isValide' => (bool) $lot->isValide,
                    'isVendus' => (bool) $lot->isVendus,
                    'est_utilise' => $estUtilise,
                    'source' => $lot->source,
                    // Actions autorisées côté serveur : modification/suppression seulement si lot manuel non validé ;
                    // (RG-SEC-01) un lot importé est en lecture seule tant qu'il n'est pas validé.
                    'peut_modifier' => ! $lot->estImporte() && ! $lot->isValide && ! $lot->isVendus,
                    'peut_supprimer' => ! $lot->estImporte() && ! $lot->isValide,
                    'peut_valider' => ! $lot->isValide && ! $lot->estPerime(),
                    'peut_annuler_validation' => $lot->isValide && ! $estUtilise,
                ];
            });

        return $this->ok([
            'index' => ['id' => $entreeIndex->id, 'ref' => $entreeIndex->ref_entree, 'motif' => $entreeIndex->motif ?? ''],
            'data' => $rows,
        ]);
    }

    /** Création d'un lot avec calculs tarifaires. */
    public function store(StoreEntreeDetailRequest $request, EntreeIndex $entreeIndex): JsonResponse
    {
        abort_unless($entreeIndex->etat, 404);

        return DB::transaction(function () use ($request, $entreeIndex): JsonResponse {
            $article = Article::query()->where('etat', 1)->lockForUpdate()->findOrFail($request->integer('article_id'));

            $base = $this->tarifs($request, $article);
            $base['entree_indices_id'] = $entreeIndex->id;
            $base['lot'] = LotNumber::generer($article->id);
            $base['qte_initial'] = 0;
            EntreeDetail::create($base);

            return $this->ok(['message' => 'Lot enregistré.'], 201);
        });
    }

    /** Modification d'un lot manuel qui n'est ni validé ni vendu. */
    public function update(StoreEntreeDetailRequest $request, EntreeDetail $entreeDetail): JsonResponse
    {
        return DB::transaction(function () use ($request, $entreeDetail): JsonResponse {
            $lot = EntreeDetail::query()->lockForUpdate()->findOrFail($entreeDetail->id);
            $article = Article::query()->where('etat', 1)->lockForUpdate()->findOrFail($request->integer('article_id'));

            if ($lot->isValide || $lot->isVendus || ((int) $lot->etat) !== 1) {
                return $this->echec('Un lot validé ou vendu ne peut plus être modifié.');
            }
            if ($lot->estImporte()) {
                return $this->echec('Un lot importé ne peut pas être modifié.');
            }

            $lot->update($this->tarifs($request, $article));

            return $this->ok(['message' => 'Lot enregistré.']);
        });
    }

    /**
     * RG-VAL-01 — Validation d'un lot :
     * refus si périmé ; sinon intégration au stock global + verrous.
     */
    public function valider(EntreeDetail $entreeDetail): JsonResponse
    {
        return DB::transaction(function () use ($entreeDetail): JsonResponse {
            $detail = EntreeDetail::query()->lockForUpdate()->findOrFail($entreeDetail->id);
            $article = Article::query()->lockForUpdate()->findOrFail($detail->article_id);

            if (((int) $detail->etat) !== 1 || $detail->isValide || $detail->isVendus) {
                return $this->echec('Ce lot ne peut pas être validé.');
            }

            if ($detail->estPerime()) {
                return $this->echec('Cet article est périmé !');
            }

            $qte = (int) $detail->qte_entree;

            $detail->update([
                'qte_initial' => (int) $article->stock,
                'stock_restant_lot' => $qte,
                'stock_dispo' => (int) $article->stock + $qte,
                'isValide' => true,
            ]);

            $this->recalculeNbLotsDispo($article);
            $article->increment('stock', $qte);

            StockMouvement::ecrire(
                $detail->article_id,
                StockMouvement::TYPE_ENTREE,
                $qte,
                (int) $article->stock - $qte,
                (int) $article->stock,
                $detail->id,
                $detail->lot,
                "Validation du lot {$detail->lot}",
            );

            return $this->ok(['message' => 'Lot validé et intégré au stock.']);
        });
    }

    /**
     * RG-VAL-02 — Annulation de validation :
     * refusée si le lot a déjà été consommé (évite la désynchronisation du stock).
     */
    public function annulerValidation(EntreeDetail $entreeDetail): JsonResponse
    {
        return DB::transaction(function () use ($entreeDetail): JsonResponse {
            $detail = EntreeDetail::query()->lockForUpdate()->findOrFail($entreeDetail->id);
            $article = Article::query()->lockForUpdate()->findOrFail($detail->article_id);

            if (! $detail->isValide || ((int) $detail->etat) !== 1) {
                return $this->echec("Ce lot n'est pas validé.");
            }

            // Garde-fou anti-désynchronisation : aucune unité du lot ne doit avoir été consommée
            if ((int) $detail->stock_restant_lot !== (int) $detail->qte_entree) {
                return $this->echec('Impossible : ce lot a déjà été partiellement consommé (ventes ou sorties).');
            }

            $qte = (int) $detail->qte_entree;
            $nouveauStock = (int) $article->stock - $qte;

            if ($nouveauStock < 0) {
                return $this->echec('Opération invalide : le stock résultant serait négatif.');
            }

            $article->decrement('stock', $qte);

            $detail->update([
                'qte_initial' => $nouveauStock,
                'stock_restant_lot' => 0,
                'stock_dispo' => $nouveauStock,
                'isValide' => false,
            ]);

            $this->recalculeNbLotsDispo($article->refresh());

            StockMouvement::ecrire(
                $detail->article_id,
                StockMouvement::TYPE_ENTREE_ANNULEE,
                -$qte,
                $nouveauStock + $qte,
                $nouveauStock,
                $detail->id,
                $detail->lot,
                "Annulation de la validation du lot {$detail->lot}",
            );

            return $this->ok(['message' => 'Validation annulée, stock retiré.']);
        });
    }

    public function destroy(EntreeDetail $entreeDetail): JsonResponse
    {
        return DB::transaction(function () use ($entreeDetail): JsonResponse {
            $detail = EntreeDetail::query()->lockForUpdate()->findOrFail($entreeDetail->id);

            if ($detail->isValide || $detail->isVendus) {
                return $this->echec('Impossible de supprimer un lot validé.');
            }
            if ($detail->estImporte()) {
                return $this->echec('Un lot importé ne peut pas être supprimé.');
            }

            $detail->update(['etat' => 0]);

            return $this->ok(['message' => 'Lot supprimé.']);
        });
    }

    /** Articles actifs pour les formulaires (select avec infos de stock). */
    public function articles(): JsonResponse
    {
        abort_unless(Gate::any(['pharma.entrees', 'pharma.sorties']), 403);

        $rows = Article::query()
            ->with('uniteRelation:id,nomUnite,nomComplet')
            ->where(['statut' => 1, 'etat' => 1])
            ->orderBy('designation')
            ->get()
            ->map(fn (Article $a): array => [
                'id' => $a->id,
                'designation' => $a->designation,
                'presentation' => (int) $a->presentation,
                'unite' => $a->uniteRelation?->nomComplet ?? '',
                'stock' => (int) $a->stock,
                'stock_boites' => $a->stockBoites(),
                'seuil' => (int) $a->seuil,
                'alerte_seuil' => $a->stock <= $a->seuil,
                'epuise' => $a->stock == 0,
            ]);

        return $this->ok(['data' => $rows]);
    }

    /** Import Excel des lignes d'entrée (RG-IMP-01 à 04). */
    public function import(Request $request, EntreeIndex $entreeIndex): JsonResponse
    {
        abort_unless($entreeIndex->etat, 404);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls']]);

        try {
            $import = new EntreeDetailImport($entreeIndex->id);
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            report($e);

            return $this->echec("Erreur lors de l'import Excel.", 500);
        }

        // Recomptage en excluant les lignes supprimées logiquement
        $entreeIndex->update(['nb_article' => $entreeIndex->details()->where('etat', 1)->count()]);

        return $this->ok([
            'message' => $import->importes().' ligne(s) importée(s).',
            'importes' => $import->importes(),
            'ignorees' => $import->lignesIgnorees(),
        ]);
    }

    /**
     * Prix et montants d'un lot.
     * RG-LOT-02 : PU de vente brut = PU achat unitaire × (1 + P/100) ; RG-PRIX : le PU de vente (brut) fait foi.
     *
     * @return array<string, mixed>
     */
    private function tarifs(StoreEntreeDetailRequest $request, Article $article): array
    {
        $taux = Pourcentage::taux();
        $presentation = max(1, (int) $article->presentation);
        $puAchatUnitaire = bcdiv((string) $request->float('prix_achat_boite'), (string) $presentation, 2);
        $prixUnitaire = bcadd(
            $puAchatUnitaire,
            bcdiv(bcmul($puAchatUnitaire, (string) $taux, 4), '100', 4),
            2
        );
        $qte = $request->integer('qte_entree');

        return [
            'article_id' => $article->id,
            'qte_entree' => $qte,
            'prix_achat_boite' => (string) $request->float('prix_achat_boite'),
            'prix_unitaire' => $prixUnitaire,
            'montant_entree' => bcmul($puAchatUnitaire, (string) $qte, 2),
            'montant_gain_brut' => bcmul($prixUnitaire, (string) $qte, 2),
            'date_peremption' => $request->filled('date_peremption')
                ? Carbon::parse($request->string('date_peremption'))->toDateString()
                : null,
        ];
    }

    private function recalculeNbLotsDispo(Article $article): void
    {
        $nb = $article->lotsDisponibles()->count();
        EntreeDetail::query()->where('article_id', $article->id)->update(['nb_lot_dispo' => $nb]);
    }
}

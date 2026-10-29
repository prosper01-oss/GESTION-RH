<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Article;
use App\Models\Pharma\EntreeDetail;
use App\Models\Pharma\EntreeIndex;
use App\Models\Pharma\Inventaire;
use App\Models\Pharma\InventaireDetail;
use App\Models\Pharma\Pourcentage;
use App\Models\Pharma\StockMouvement;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Inventaire physique.
 * Séance de comptage (théorie vs compté) puis application normalisée des écarts :
 * positif → lot d'inventaire créé et intégré ; négatif → consommation FEFO des lots.
 */
class InventaireController extends Controller
{
    use RepondEnJson;

    public function index(): JsonResponse
    {
        $rows = Inventaire::query()
            ->with('user:id,name')
            ->withCount('lignes')
            ->withSum('lignes as total_ecart', 'ecart')
            ->withSum('lignes as total_theorique', 'theorique')
            ->withSum('lignes as total_compte', 'compte')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Inventaire $inventaire): array => [
                'id' => $inventaire->id,
                'reference' => $inventaire->reference,
                'date' => $inventaire->date_inventaire->format('Y-m-d'),
                'statut' => $inventaire->statut,
                'nb_articles' => (int) $inventaire->lignes_count,
                'total_theorique' => (int) $inventaire->total_theorique,
                'total_compte' => (int) $inventaire->total_compte,
                'total_ecart' => (int) $inventaire->total_ecart,
                'user' => $inventaire->user?->name ?? '',
                'motif' => $inventaire->motif ?? '',
            ]);

        return $this->ok(['data' => $rows]);
    }

    public function show(Inventaire $inventaire): JsonResponse
    {
        $lignes = $inventaire->lignes()
            ->with('article:id,designation')
            ->orderBy('id')
            ->get()
            ->map(fn (InventaireDetail $ligne): array => [
                'id' => $ligne->id,
                'article_id' => $ligne->article_id,
                'designation' => $ligne->article?->designation ?? '—',
                'theorique' => (int) $ligne->theorique,
                'compte' => $ligne->compte === null ? null : (int) $ligne->compte,
                'ecart' => (int) $ligne->ecart,
            ]);

        return $this->ok([
            'inventaire' => [
                'id' => $inventaire->id,
                'reference' => $inventaire->reference,
                'date' => $inventaire->date_inventaire->format('Y-m-d'),
                'statut' => $inventaire->statut,
            ],
            'data' => $lignes,
        ]);
    }

    /** Création d'une séance (brouillon) avec une ligne par article actif. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_inventaire' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($validated): JsonResponse {
            $articles = Article::query()->where(['statut' => 1, 'etat' => 1])->orderBy('designation')->get();
            if ($articles->isEmpty()) {
                return $this->echec('Aucun article actif à inventorier.');
            }

            $inventaire = Inventaire::create([
                'reference' => $this->genererReference(),
                'date_inventaire' => $validated['date_inventaire'],
                'statut' => Inventaire::STATUT_BROUILLON,
                'motif' => $validated['motif'] ?? null,
                'user_id' => auth()->id(),
            ]);

            foreach ($articles as $article) {
                InventaireDetail::create([
                    'inventaire_id' => $inventaire->id,
                    'article_id' => $article->id,
                    'theorique' => (int) $article->stock,
                    'compte' => null,
                    'ecart' => 0,
                ]);
            }

            return $this->ok(['message' => 'Inventaire créé, comptez les quantités.', 'id' => $inventaire->id], 201);
        });
    }

    /** Saisie du comptage d'une ligne (écart recalculé). */
    public function updateLigne(Request $request, InventaireDetail $inventaireDetail): JsonResponse
    {
        $validated = $request->validate(['compte' => ['required', 'integer', 'min:0']]);

        return DB::transaction(function () use ($validated, $inventaireDetail): JsonResponse {
            $ligne = InventaireDetail::query()->lockForUpdate()->findOrFail($inventaireDetail->id);
            $inventaire = $ligne->inventaire()->lockForUpdate()->firstOrFail();

            if ($inventaire->statut !== Inventaire::STATUT_BROUILLON) {
                return $this->echec("La saisie n'est possible que sur un brouillon.");
            }

            $ligne->update([
                'compte' => $validated['compte'],
                'ecart' => $validated['compte'] - (int) $ligne->theorique,
            ]);

            return $this->ok(['message' => 'Comptage enregistré.']);
        });
    }

    /** Verrouillage de la séance : au moins un article doit être compté. */
    public function valider(Inventaire $inventaire): JsonResponse
    {
        return DB::transaction(function () use ($inventaire): JsonResponse {
            $inventaire = Inventaire::query()->lockForUpdate()->findOrFail($inventaire->id);

            if ($inventaire->statut !== Inventaire::STATUT_BROUILLON) {
                return $this->echec('Seul un brouillon peut être validé.');
            }
            if ($inventaire->lignes()->whereNull('compte')->count() === $inventaire->lignes()->count()) {
                return $this->echec("Aucun article n'est encore compté.");
            }

            $inventaire->update(['statut' => Inventaire::STATUT_VALIDE]);

            return $this->ok(['message' => 'Inventaire validé, prêt à être appliqué au stock.']);
        });
    }

    /**
     * Application des écarts au stock :
     * écart positif → lot d'inventaire créé et intégré ; écart négatif → consommation FEFO.
     * L'écart est recalculé sur le stock courant au moment de l'application.
     * Tout échec annule l'ensemble de l'application.
     */
    public function appliquer(Inventaire $inventaire): JsonResponse
    {
        try {
            return DB::transaction(function () use ($inventaire): JsonResponse {
                $inventaire = Inventaire::query()->with('lignes')->lockForUpdate()->findOrFail($inventaire->id);

                if (in_array($inventaire->statut, [Inventaire::STATUT_APPLIQUE, Inventaire::STATUT_ANNULE], true)) {
                    return $this->echec('Cet inventaire est déjà terminé.');
                }
                if ($inventaire->lignes()->whereNull('compte')->count() === $inventaire->lignes()->count()) {
                    return $this->echec("Aucun article n'est encore compté.");
                }

                $taux = Pourcentage::taux();
                $entreeIndex = null;
                $ajustes = 0;

                foreach ($inventaire->lignes as $ligne) {
                    if ($ligne->compte === null) {
                        continue; // article non compté : aucun ajustement
                    }

                    $article = Article::query()->lockForUpdate()->findOrFail($ligne->article_id);
                    $stockCourant = (int) $article->stock;
                    $delta = (int) $ligne->compte - $stockCourant;

                    if ($delta === 0) {
                        $this->recalculeNbLotsDispo($article);

                        continue;
                    }

                    if ($delta > 0) {
                        $entreeIndex ??= EntreeIndex::create([
                            'ref_entree' => $inventaire->reference,
                            'motif' => 'Inventaire physique '.$inventaire->reference,
                            'nb_article' => 0,
                            'montant_total' => 0,
                            'etat' => true,
                        ]);

                        $lot = $this->creerLotInventaire($entreeIndex, $article, $delta, $inventaire->reference, $taux);
                        $article->increment('stock', $delta);
                        $entreeIndex->increment('nb_article');
                        $entreeIndex->increment('montant_total', (float) $lot->montant_entree);

                        StockMouvement::ecrire(
                            $article->id,
                            StockMouvement::TYPE_INVENTAIRE,
                            $delta,
                            $stockCourant,
                            $stockCourant + $delta,
                            $lot->id,
                            $inventaire->reference,
                            "Inventaire {$inventaire->reference} : écart de +{$delta} (lot {$lot->lot})",
                        );

                        $ligne->update(['entree_detail_id' => $lot->id]);
                    } else {
                        $reste = $this->consommerFefo($article, -$delta, $inventaire->reference);

                        if ($reste > 0) {
                            throw new DomainException(
                                "Stock insuffisant pour absorber l'écart négatif de {$article->designation} "
                                ."(reste $reste unité(s) non ajustées)."
                            );
                        }
                    }

                    $this->recalculeNbLotsDispo($article);
                    $ajustes++;
                }

                $inventaire->update(['statut' => Inventaire::STATUT_APPLIQUE]);

                return $this->ok(['message' => "Inventaire appliqué : $ajustes article(s) ajusté(s)."]);
            });
        } catch (DomainException $e) {
            return $this->echec($e->getMessage());
        }
    }

    /** Annulation terminale d'une séance non appliquée (aucun effet sur le stock). */
    public function destroy(Inventaire $inventaire): JsonResponse
    {
        if (in_array($inventaire->statut, [Inventaire::STATUT_APPLIQUE, Inventaire::STATUT_ANNULE], true)) {
            return $this->echec('Cet inventaire est déjà terminé.');
        }

        $inventaire->update(['statut' => Inventaire::STATUT_ANNULE]);

        return $this->ok(['message' => 'Inventaire annulé.']);
    }

    /**
     * Retire $reste unités des lots validés de l'article, le lot le plus proche de la péremption en premier.
     * Renvoie la quantité qui n'a pas pu être retirée.
     */
    private function consommerFefo(Article $article, int $reste, string $reference): int
    {
        $lots = EntreeDetail::query()
            ->where([
                ['article_id', $article->id],
                ['isValide', true],
                ['etat', true],
            ])
            ->where('stock_restant_lot', '>', 0)
            ->orderByRaw('date_peremption IS NULL ASC')
            ->orderBy('date_peremption')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($lots as $lot) {
            if ($reste <= 0) {
                break;
            }

            $prendre = min($reste, (int) $lot->stock_restant_lot);
            if ($prendre <= 0) {
                continue;
            }

            $lot->update([
                'stock_restant_lot' => max(0, (int) $lot->stock_restant_lot - $prendre),
                'stock_dispo' => max(0, (int) $lot->stock_dispo - $prendre),
                'isVendus' => (int) $lot->stock_restant_lot - $prendre <= 0,
            ]);

            $stockAvant = (int) $article->stock;
            $article->decrement('stock', $prendre);

            StockMouvement::ecrire(
                $article->id,
                StockMouvement::TYPE_INVENTAIRE,
                -$prendre,
                $stockAvant,
                $stockAvant - $prendre,
                $lot->id,
                $reference,
                "Inventaire {$reference} : écart négatif (lot {$lot->lot})",
            );

            $reste -= $prendre;
        }

        return $reste;
    }

    /** Lot spécial d'inventaire : coût = dernier prix d'achat connu du lot, sinon 0. */
    private function creerLotInventaire(EntreeIndex $entreeIndex, Article $article, int $qte, string $reference, float $taux): EntreeDetail
    {
        $presentation = max(1, (int) $article->presentation);
        $prixBoite = (float) (EntreeDetail::query()
            ->where('article_id', $article->id)
            ->where('isValide', 1)
            ->latest('id')
            ->value('prix_achat_boite') ?? 0);

        $puAchat = bcdiv((string) $prixBoite, (string) $presentation, 2);
        $prixVente = bcadd($puAchat, bcdiv(bcmul($puAchat, (string) $taux, 4), '100', 4), 2);

        return EntreeDetail::create([
            'entree_indices_id' => $entreeIndex->id,
            'article_id' => $article->id,
            'lot' => $reference.'-A'.str_pad((string) $article->id, 4, '0', STR_PAD_LEFT),
            'source' => EntreeDetail::SOURCE_MANUEL,
            'qte_initial' => (int) $article->stock,
            'qte_entree' => $qte,
            'stock_restant_lot' => $qte,
            'stock_dispo' => (int) $article->stock + $qte,
            'prix_achat_boite' => (string) $prixBoite,
            'prix_unitaire' => $prixVente,
            'montant_entree' => bcmul($puAchat, (string) $qte, 2),
            'montant_gain_brut' => bcmul($prixVente, (string) $qte, 2),
            'date_peremption' => null,
            'isValide' => true,
            'isVendus' => false,
            'etat' => true,
        ]);
    }

    private function recalculeNbLotsDispo(Article $article): void
    {
        EntreeDetail::query()->where('article_id', $article->id)->update(['nb_lot_dispo' => $article->lotsDisponibles()->count()]);
    }

    private function genererReference(): string
    {
        $base = 'INV-'.now()->format('Ymd');

        for ($i = 1; $i <= 100; $i++) {
            $ref = $base.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            if (! Inventaire::query()->where('reference', $ref)->exists()) {
                return $ref;
            }
        }

        return $base.'-'.strtoupper(substr(uniqid(), -5));
    }
}

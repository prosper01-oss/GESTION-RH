<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pharma\StorePanierRequest;
use App\Models\Pharma\Analyse;
use App\Models\Pharma\Article;
use App\Models\Pharma\EntreeDetail;
use App\Models\Pharma\Kit;
use App\Models\Pharma\Panier;
use App\Models\Pharma\Service;
use App\Models\Pharma\StockMouvement;
use App\Services\Pharma\Docteurs;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Panier de caisse. Le stock des médicaments est réservé dès l'ajout au panier (FEFO/FIFO),
 * une ligne de panier étant créée par lot consommé.
 */
class PanierController extends Controller
{
    use RepondEnJson;

    /** Référentiel articles pour l'onglet Médicaments de la caisse. */
    public function articles(): JsonResponse
    {
        $rows = Article::query()
            ->with('uniteRelation:id,nomUnite,nomComplet')
            ->where(['statut' => 1, 'etat' => 1])
            ->orderBy('designation')
            ->get()
            ->map(function (Article $a): array {
                $vendable = $this->stockVendable($a);

                return [
                    'id' => $a->id,
                    'designation' => $a->designation,
                    'presentation' => (int) $a->presentation,
                    'unite' => $a->uniteRelation?->nomComplet ?? '',
                    'stock' => (int) $a->stock,
                    'stock_vendable' => $vendable,
                    'epuise' => $vendable == 0,
                    'seuil_atteint' => $vendable <= $a->seuil,
                ];
            });

        return $this->ok(['data' => $rows]);
    }

    public function index(): JsonResponse
    {
        $paniers = Panier::query()->where('user_id', auth()->id())->orderBy('id')->get();

        $totalBrut = '0';
        foreach ($paniers as $ligne) {
            $totalBrut = bcadd($totalBrut, (string) $ligne->montant_brut, 2);
        }

        $rows = $paniers->map(fn (Panier $ligne): array => [
            'id' => $ligne->id,
            'type' => $ligne->type,
            'designation' => $this->designationLigne($ligne),
            'p_u_brut' => $ligne->p_u_brut,
            'qte' => (int) $ligne->qte,
            'montant_brut' => $ligne->montant_brut,
            'ordre_soins_id' => $ligne->ordre_soins_id,
        ]);

        return $this->ok([
            'data' => $rows,
            'total' => (float) $totalBrut,
            // Nom du patient de la consultation du panier, pour préremplir le client de la vente
            'patient' => $paniers->firstWhere(fn (Panier $ligne): bool => $ligne->consultation_id !== null)?->nom_patient
                ?? $paniers->firstWhere(fn (Panier $ligne): bool => $ligne->ordre_soins_id !== null)?->nom_patient,
        ]);
    }

    public function store(StorePanierRequest $request): JsonResponse
    {
        try {
            return DB::transaction(fn (): JsonResponse => match ($request->string('page_type')->toString()) {
                'article' => $this->reserverArticle($request),
                'service' => $this->ajouterService($request),
                'analyse' => $this->ajouterAnalyses($request),
                'kit' => $this->ajouterKit($request),
                default => $this->ajouterConsultation($request),
            });
        } catch (DomainException $e) {
            // « deja_ajoutee » et « insuffisant » sont des codes que l'interface traduit en message.
            return response()->json(['message' => $e->getMessage(), 'code' => $e->getMessage()], 422);
        }
    }

    /** Retrait d'une ligne avec restitution du stock réservé. */
    public function destroy(Panier $panier): JsonResponse
    {
        abort_unless((int) $panier->user_id === (int) auth()->id(), 404);

        return $this->retirer($panier->id, 'Retrait ligne panier');
    }

    /** Vidage complet du panier avec restitution du stock réservé. */
    public function clear(): JsonResponse
    {
        return $this->retirer(null, 'Vidage du panier (caisse)');
    }

    private function retirer(?int $panierId, string $libelle): JsonResponse
    {
        return DB::transaction(function () use ($panierId, $libelle): JsonResponse {
            $lignes = Panier::query()
                ->where('user_id', auth()->id())
                ->when($panierId, fn ($q) => $q->where('id', $panierId))
                ->lockForUpdate()
                ->get();

            foreach ($lignes as $ligne) {
                if ($ligne->entree_detail_id && $ligne->article_id) {
                    $this->restituer($ligne, StockMouvement::TYPE_PANIER_RETRAIT, $libelle);
                }
                $ligne->delete();
            }

            $supprimes = $lignes->count();

            return $this->ok(['message' => "$supprimes ligne(s) supprimée(s).", 'data' => $supprimes]);
        });
    }

    /** Remet en stock la quantité réservée par une ligne de panier. */
    private function restituer(Panier $ligne, string $type, string $libelle): void
    {
        $avant = (int) DB::table('pharma_articles')->where('id', $ligne->article_id)->value('stock');
        $qte = (int) $ligne->qte;

        EntreeDetail::query()->where('id', $ligne->entree_detail_id)->update([
            'stock_restant_lot' => DB::raw('stock_restant_lot + '.$qte),
            'stock_dispo' => DB::raw('stock_dispo + '.$qte),
            'isVendus' => false,
        ]);
        Article::query()->where('id', $ligne->article_id)->increment('stock', $qte);

        StockMouvement::ecrire($ligne->article_id, $type, $qte, $avant, $avant + $qte, $ligne->entree_detail_id, null, $libelle);
    }

    /** Stock vendable : somme du restant des lots validés non périmés. */
    private function stockVendable(Article $article): int
    {
        return (int) EntreeDetail::query()
            ->where('article_id', $article->id)
            ->where('isValide', 1)
            ->where('etat', 1)
            ->where('stock_restant_lot', '>', 0)
            ->where(fn ($q) => $q->whereNull('date_peremption')->orWhereDate('date_peremption', '>=', now()->toDateString()))
            ->sum('stock_restant_lot');
    }

    /* ------------------------------------------------------------------ */
    /* Ajouts par nature */
    /* ------------------------------------------------------------------ */

    private function ajouterConsultation(StorePanierRequest $request): JsonResponse
    {
        $service = Service::query()->where(['isConsult' => 1, 'etat' => true])->first();
        if (! $service) {
            throw new DomainException('Aucun service de consultation configuré. Créez-le dans Gestion Pharmacie > Services en cochant « Service de consultation ».');
        }

        // Le docteur doit être une fiche active du personnel RH, de la fonction choisie
        if (! Docteurs::trouver($request->integer('docteur'), $request->string('type_docteur')->toString())) {
            throw new DomainException('Le docteur sélectionné est introuvable pour ce type.');
        }

        // Prix lu en base, jamais envoyé par le client
        $prix = (string) $service->prix_service;

        $this->enregistrerLigne($request, [
            'type' => 'Consultation',
            'consultation_id' => $service->id,
            'nom_patient' => $request->string('nom_patient')->trim(),
            'sex_patient' => $request->boolean('sex_patient'),
            'age_patient' => $request->integer('age_patient'),
            'unite_age' => $request->boolean('unite_age'),
            'type_docteur' => $request->string('type_docteur'),
            'docteur' => (string) $request->integer('docteur'),
            'p_u_brut' => $prix,
            'qte' => 1,
            'montant_brut' => $prix,
        ]);

        return $this->ok(['message' => 'Consultation ajoutée au panier.'], 201);
    }

    private function ajouterService(StorePanierRequest $request): JsonResponse
    {
        $service = Service::query()->where(['id' => $request->integer('service_id'), 'etat' => true])->first();
        if (! $service) {
            throw new DomainException('Service introuvable.');
        }

        $this->refuserDoublon($request, 'service_id', $service->id);

        $prix = (string) $service->prix_service;
        $qte = $request->integer('qte');

        $this->enregistrerLigne($request, [
            'type' => 'Service',
            'service_id' => $service->id,
            'p_u_brut' => $prix,
            'qte' => $qte,
            'montant_brut' => bcmul($prix, (string) $qte, 2),
        ]);

        return $this->ok(['message' => 'Service ajouté au panier.'], 201);
    }

    private function ajouterKit(StorePanierRequest $request): JsonResponse
    {
        $kit = Kit::query()->where(['id' => $request->integer('kit_id'), 'etat' => true])->first();
        if (! $kit) {
            throw new DomainException('Kit introuvable.');
        }

        $this->refuserDoublon($request, 'kit_id', $kit->id);

        $prix = (string) $kit->prix_kit;
        $qte = $request->integer('qte');

        $this->enregistrerLigne($request, [
            'type' => 'Kit',
            'kit_id' => $kit->id,
            'p_u_brut' => $prix,
            'qte' => $qte,
            'montant_brut' => bcmul($prix, (string) $qte, 2),
        ]);

        return $this->ok(['message' => 'Kit ajouté au panier.'], 201);
    }

    /** Une seule ligne d'analyses par panier : montant = somme des prix en base. */
    private function ajouterAnalyses(StorePanierRequest $request): JsonResponse
    {
        $ids = collect($request->input('analyse_id', []))->map(fn ($v): int => (int) $v)->unique()->values();
        if ($ids->isEmpty()) {
            throw new DomainException('Sélectionnez au moins une analyse.');
        }

        $montantTotal = Analyse::query()->whereIn('id', $ids)->sum('prix');

        $existantAutre = Panier::query()
            ->where('user_id', auth()->id())
            ->whereNotNull('analyse_id')
            ->when($request->filled('id_panier'), fn ($q) => $q->where('id', '!=', $request->integer('id_panier')))
            ->exists();
        if ($existantAutre) {
            throw new DomainException('deja_ajoutee');
        }

        $this->enregistrerLigne($request, [
            'type' => 'Analyse',
            'analyse_id' => $ids->implode(','),
            'p_u_brut' => $montantTotal,
            'qte' => 1,
            'montant_brut' => $montantTotal,
        ]);

        return $this->ok(['message' => 'Analyse(s) ajoutée(s) au panier.'], 201);
    }

    /**
     * Réservation immédiate FEFO/FIFO d'un article : une ligne de panier est créée PAR LOT consommé.
     */
    private function reserverArticle(StorePanierRequest $request): JsonResponse
    {
        $qte = $request->integer('qte');

        $article = Article::query()->lockForUpdate()->find($request->integer('article_id'));
        if (! $article || ((int) $article->etat) !== 1) {
            throw new DomainException('Article introuvable.');
        }
        if ((int) $article->stock < $qte) {
            throw new DomainException('insuffisant');
        }

        if ($request->filled('id_panier')) {
            // Modification : restauration préalable des réservations existantes
            $anciennes = Panier::query()
                ->where('user_id', auth()->id())
                ->where('article_id', $article->id)
                ->lockForUpdate()
                ->get();

            foreach ($anciennes as $ancienne) {
                if ($ancienne->entree_detail_id) {
                    $this->restituer($ancienne, StockMouvement::TYPE_PANIER_RETRAIT, 'Retrait panier (édition de ligne)');
                }
                $ancienne->delete();
            }
            $article->refresh();
        } elseif (Panier::query()->where(['user_id' => auth()->id(), 'article_id' => $article->id])->exists()) {
            throw new DomainException('deja_ajoutee');
        }

        // Stock vendable = stock des lots non périmés (les périmés sont exclus de la vente)
        if ($this->stockVendable($article) < $qte) {
            throw new DomainException('insuffisant');
        }

        // Lots validés disponibles : péremption la plus proche d'abord (lots sans date en dernier), puis FIFO
        $lots = EntreeDetail::query()
            ->where([['article_id', $article->id], ['isValide', true], ['etat', true]])
            ->where('stock_restant_lot', '>', 0)
            ->where(fn ($q) => $q->whereNull('date_peremption')->orWhereDate('date_peremption', '>=', now()->toDateString()))
            ->orderByRaw('date_peremption IS NULL ASC')
            ->orderBy('date_peremption')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        $reste = $qte;
        foreach ($lots as $lot) {
            if ($reste <= 0) {
                break;
            }

            $prendre = min($reste, (int) $lot->stock_restant_lot);
            if ($prendre <= 0) {
                continue;
            }

            Panier::create([
                'type' => 'Article',
                'article_id' => $article->id,
                'entree_detail_id' => $lot->id,
                'p_u_brut' => $lot->prix_unitaire,
                'qte' => $prendre,
                'montant_brut' => bcmul((string) $lot->prix_unitaire, (string) $prendre, 2),
                'user_id' => auth()->id(),
            ]);

            // Les lignes sont verrouillées : les nouvelles valeurs sont calculées ici, sans dépendre du SGBD.
            $restantLot = (int) $lot->stock_restant_lot - $prendre;
            $lot->update([
                'stock_restant_lot' => max(0, $restantLot),
                'stock_dispo' => max(0, (int) $lot->stock_dispo - $prendre),
                'isVendus' => $restantLot <= 0,
            ]);

            $stockAvant = (int) $article->stock;
            $article->decrement('stock', $prendre);

            StockMouvement::ecrire($article->id, StockMouvement::TYPE_PANIER, -$prendre, $stockAvant, $stockAvant - $prendre, $lot->id, null, 'Réservation caisse (panier)');

            $reste -= $prendre;
        }

        if ($reste > 0) {
            throw new DomainException('insuffisant'); // course entre requêtes
        }

        return $this->ok(['message' => 'Médicament ajouté au panier.'], 201);
    }

    /**
     * Crée la ligne, ou met à jour celle de l'utilisateur si id_panier est fourni.
     *
     * @param  array<string, mixed>  $payload
     */
    private function enregistrerLigne(StorePanierRequest $request, array $payload): void
    {
        if ($request->filled('id_panier')) {
            Panier::query()->where('user_id', auth()->id())->findOrFail($request->integer('id_panier'))->update($payload);

            return;
        }

        Panier::create($payload + ['user_id' => auth()->id()]);
    }

    private function refuserDoublon(StorePanierRequest $request, string $colonne, int $id): void
    {
        $existant = Panier::query()
            ->where(['user_id' => auth()->id(), $colonne => $id])
            ->when($request->filled('id_panier'), fn ($q) => $q->where('id', '!=', $request->integer('id_panier')))
            ->exists();

        if ($existant) {
            throw new DomainException('deja_ajoutee');
        }
    }

    private function designationLigne(Panier $ligne): string
    {
        if ($ligne->consultation_id) {
            return Service::query()->where('id', $ligne->consultation_id)->value('nom_service') ?? 'Consultation';
        }
        if ($ligne->article_id) {
            return Article::query()->where('id', $ligne->article_id)->value('designation') ?? '—';
        }
        if ($ligne->service_id) {
            return Service::query()->where('id', $ligne->service_id)->value('nom_service') ?? '—';
        }
        if ($ligne->analyse_id) {
            $noms = Analyse::query()->whereIn('id', array_filter(explode(',', (string) $ligne->analyse_id)))->pluck('nom')->implode(', ');

            return $noms ?: '—';
        }
        if ($ligne->kit_id) {
            return Kit::query()->where('id', $ligne->kit_id)->value('nom_kit') ?? '—';
        }

        return '—';
    }
}

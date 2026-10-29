<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Api\Pharma\Concerns\RepondEnJson;
use App\Http\Controllers\Controller;
use App\Models\Pharma\Article;
use App\Models\Pharma\EntreeDetail;
use App\Models\Pharma\Vente;
use App\Models\Pharma\VenteDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord de la Pharmacie : chiffre d'affaires, stock critique, péremptions, pertes.
 */
class DashboardController extends Controller
{
    use RepondEnJson;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        $today = Carbon::today();

        // Période filtrée (par défaut : mois courant)
        $debut = $request->filled('date_debut') ? Carbon::parse($request->query('date_debut'))->startOfDay() : Carbon::now()->startOfMonth();
        $fin = $request->filled('date_fin') ? Carbon::parse($request->query('date_fin'))->endOfDay() : Carbon::now();

        if ($fin->lt($debut)) {
            [$debut, $fin] = [$fin->copy()->startOfDay(), $debut->copy()->endOfDay()];
        }

        $caPeriode = (float) Vente::query()->whereBetween('created_at', [$debut, $fin])->sum('montant_brut');
        $nbVentes = Vente::query()->whereBetween('created_at', [$debut, $fin])->count();

        $souSeuil = fn () => Article::query()->where('etat', 1)->where('statut', 1)->whereColumn('stock', '<=', 'seuil');

        $ruptures = $souSeuil()->orderBy('stock')->limit(8)->get(['id', 'designation', 'stock', 'seuil']);

        $peremption30j = EntreeDetail::query()
            ->with('article:id,designation,presentation')
            ->where('etat', 1)
            ->where('isValide', 1)
            ->where('stock_restant_lot', '>', 0)
            ->whereNotNull('date_peremption')
            ->whereBetween('date_peremption', [$today, $today->copy()->addDays(30)])
            ->orderBy('date_peremption')
            ->limit(8)
            ->get()
            ->map(fn (EntreeDetail $lot): array => [
                'id' => $lot->id,
                'article' => $lot->article?->designation,
                'lot' => $lot->lot,
                'restant' => (int) $lot->stock_restant_lot,
                'date_peremption' => $lot->date_peremption?->format('Y-m-d'),
            ]);

        $pertes = DB::table('pharma_sortie_details as sd')
            ->join('pharma_sortie_indices as si', 'si.id', '=', 'sd.sortie_indices_id')
            ->where('sd.etat', 1)
            ->whereBetween('si.created_at', [$debut, $fin]);

        $pertesPeriode = (float) (clone $pertes)->sum('sd.montant_perte');
        $pertesParMotif = (clone $pertes)
            ->whereIn('si.motif', ['perte', 'casse', 'perime'])
            ->groupBy('si.motif')
            ->selectRaw('si.motif, SUM(sd.montant_perte) as total')
            ->pluck('total', 'motif')
            ->map(fn ($v): float => (float) $v);

        // Valorisation du stock au coût d'achat (CMUP)
        [$valeurStock, $stockVendableQte, $coutVendable] = $this->valorisationStock();

        // Graphique : CA quotidien sur la période
        $ventesParJour = Vente::query()
            ->selectRaw('DATE(created_at) as jour, SUM(montant_brut) as total')
            ->whereBetween('created_at', [$debut, $fin])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'jour');

        $labels = [];
        $data = [];
        for ($jour = $debut->copy(); $jour->lte($fin); $jour->addDay()) {
            $labels[] = $jour->format('d/m');
            $data[] = (float) ($ventesParJour[$jour->format('Y-m-d')] ?? 0);
        }

        $topArticles = VenteDetail::query()
            ->join('pharma_articles', 'pharma_articles.id', '=', 'pharma_vente_details.article_id')
            ->join('pharma_ventes', 'pharma_ventes.id', '=', 'pharma_vente_details.vente_id')
            ->whereNotNull('pharma_vente_details.article_id')
            ->whereBetween('pharma_ventes.created_at', [$debut, $fin])
            ->groupBy('pharma_vente_details.article_id', 'pharma_articles.designation')
            ->selectRaw('pharma_articles.designation, SUM(pharma_vente_details.qte) as qte, SUM(pharma_vente_details.montant_brut) as montant')
            ->orderByDesc('qte')
            ->limit(5)
            ->get()
            ->map(fn ($top): array => ['designation' => $top->designation, 'qte' => (int) $top->qte, 'montant' => (float) $top->montant]);

        $dernieresVentes = Vente::query()
            ->with('user:id,name')
            ->whereBetween('created_at', [$debut, $fin])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Vente $vente): array => [
                'id' => $vente->id,
                'reference' => $vente->reference_vente,
                'client' => $vente->client,
                'montant_brut' => $vente->montant_brut,
                'created_at' => $vente->created_at,
                'caissier' => $vente->user?->name,
            ]);

        return $this->ok([
            'periode' => ['debut' => $debut->toDateString(), 'fin' => $fin->toDateString(), 'personnalisee' => $request->filled('date_debut') || $request->filled('date_fin')],
            'ca' => $caPeriode,
            'nb_ventes' => $nbVentes,
            'panier_moyen' => $nbVentes > 0 ? $caPeriode / $nbVentes : 0.0,
            'valeur_stock' => $valeurStock,
            'cmup_global' => $stockVendableQte > 0 ? $coutVendable / $stockVendableQte : 0.0,
            'pertes' => $pertesPeriode,
            'pertes_par_motif' => $pertesParMotif,
            'nb_ruptures' => $souSeuil()->count(),
            'ruptures' => $ruptures,
            'peremption30j' => $peremption30j,
            'graphe' => ['labels' => $labels, 'data' => $data],
            'repartition' => $this->repartitionParNature($debut, $fin),
            'top_articles' => $topArticles,
            'dernieres_ventes' => $dernieresVentes,
        ]);
    }

    /**
     * Valorisation globale du stock restant au coût d'achat (CMUP pondéré).
     *
     * @return array{0: float, 1: float, 2: float, 3: float} valeur vendable, quantité vendable, coût vendable, coût des périmés
     */
    private function valorisationStock(): array
    {
        $presentations = Article::query()->pluck('presentation', 'id');

        $lots = EntreeDetail::query()
            ->where('isValide', 1)
            ->where('etat', 1)
            ->where('stock_restant_lot', '>', 0)
            ->get(['article_id', 'prix_achat_boite', 'prix_unitaire', 'stock_restant_lot', 'date_peremption']);

        $valeurVendable = $qteVendable = $coutVendable = $coutPerimes = 0.0;

        foreach ($lots as $lot) {
            $presentation = (int) ($presentations[$lot->article_id] ?? 0);
            $cout = $presentation > 0 ? (float) $lot->prix_achat_boite / $presentation : (float) $lot->prix_unitaire;
            $montant = $cout * (int) $lot->stock_restant_lot;

            if ($lot->date_peremption && $lot->date_peremption->toDateString() < Carbon::today()->toDateString()) {
                $coutPerimes += $montant;
            } else {
                $valeurVendable += $montant;
                $qteVendable += (int) $lot->stock_restant_lot;
                $coutVendable += $montant;
            }
        }

        return [$valeurVendable, $qteVendable, $coutVendable, $coutPerimes];
    }

    /**
     * Montant de CA par nature sur une période (résolution des lignes de vente).
     *
     * @return array<string, float>
     */
    private function repartitionParNature(Carbon $debut, Carbon $fin): array
    {
        $buckets = ['Consultation' => 0.0, 'Service' => 0.0, 'Analyse' => 0.0, 'Kit' => 0.0, 'Médicament' => 0.0];

        $details = VenteDetail::query()
            ->join('pharma_ventes', 'pharma_ventes.id', '=', 'pharma_vente_details.vente_id')
            ->whereBetween('pharma_ventes.created_at', [$debut, $fin])
            ->get(['pharma_vente_details.consultation_id', 'pharma_vente_details.service_id', 'pharma_vente_details.analyse_id',
                'pharma_vente_details.kit_id', 'pharma_vente_details.article_id', 'pharma_vente_details.montant_brut']);

        foreach ($details as $detail) {
            $nature = match (true) {
                $detail->consultation_id !== null => 'Consultation',
                $detail->service_id !== null => 'Service',
                $detail->analyse_id !== null => 'Analyse',
                $detail->kit_id !== null => 'Kit',
                $detail->article_id !== null => 'Médicament',
                default => null,
            };

            if ($nature) {
                $buckets[$nature] += (float) $detail->montant_brut;
            }
        }

        return $buckets;
    }
}

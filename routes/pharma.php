<?php

use App\Http\Controllers\Api\Pharma\AnalyseController;
use App\Http\Controllers\Api\Pharma\ArticleController;
use App\Http\Controllers\Api\Pharma\CaisseSessionController;
use App\Http\Controllers\Api\Pharma\CategorieController;
use App\Http\Controllers\Api\Pharma\CmmController;
use App\Http\Controllers\Api\Pharma\ConsultationController;
use App\Http\Controllers\Api\Pharma\DashboardController;
use App\Http\Controllers\Api\Pharma\DetailKitController;
use App\Http\Controllers\Api\Pharma\DocteurController;
use App\Http\Controllers\Api\Pharma\EchographieController;
use App\Http\Controllers\Api\Pharma\EntreeDetailController;
use App\Http\Controllers\Api\Pharma\EntreeIndexController;
use App\Http\Controllers\Api\Pharma\FournisseurController;
use App\Http\Controllers\Api\Pharma\HistoriqueVenteController;
use App\Http\Controllers\Api\Pharma\InventaireController;
use App\Http\Controllers\Api\Pharma\KitController;
use App\Http\Controllers\Api\Pharma\MouvementController;
use App\Http\Controllers\Api\Pharma\OrdreCaisseController;
use App\Http\Controllers\Api\Pharma\OrdreSoinsController;
use App\Http\Controllers\Api\Pharma\PanierController;
use App\Http\Controllers\Api\Pharma\ReapproController;
use App\Http\Controllers\Api\Pharma\ServiceController;
use App\Http\Controllers\Api\Pharma\SortieDetailController;
use App\Http\Controllers\Api\Pharma\SortieIndexController;
use App\Http\Controllers\Api\Pharma\VenteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes du module Pharmacie
|--------------------------------------------------------------------------
|
| Chargées dans le groupe authentifié de routes/api.php, sous le préfixe
| « pharma » (/api/v1/pharma/...). Chaque sous-groupe exige la permission
| du module correspondant (Gate pharma.*).
|
*/

/* Sélecteurs lus par la caisse ou la gestion : le contrôle d'accès est fait dans le contrôleur. */
Route::get('/analyses/options', [AnalyseController::class, 'options'])->middleware('can:pharma.caisse')->name('analyses.options');
Route::get('/kits/options', [KitController::class, 'options'])->middleware('can:pharma.caisse')->name('kits.options');
Route::get('/services/options', [ServiceController::class, 'options'])->name('services.options');
Route::get('/docteurs', [DocteurController::class, 'index'])->name('docteurs.index');

/* Gestion : référentiels du catalogue */
Route::middleware('can:pharma.gestion')->group(function () {
    Route::apiResource('analyses', AnalyseController::class)->except('show')->parameters(['analyses' => 'analyse']);
    Route::get('/echographies/options', [EchographieController::class, 'options'])->name('echographies.options');
    Route::apiResource('echographies', EchographieController::class)->except('show')->parameters(['echographies' => 'echographie']);
    Route::apiResource('categories', CategorieController::class)->except('show')->parameters(['categories' => 'categorie']);
    Route::get('/services/categories', [ServiceController::class, 'categories'])->name('services.categories');
    Route::apiResource('services', ServiceController::class)->except('show');
    Route::apiResource('kits', KitController::class)->except('show');
    Route::get('/detail-kits/options', [DetailKitController::class, 'options'])->name('detail-kits.options');
    Route::apiResource('detail-kits', DetailKitController::class)->except('show')->parameters(['detail-kits' => 'detailKit']);
});

/* Articles et fournisseurs */
Route::middleware('can:pharma.articles')->group(function () {
    Route::get('/fournisseurs/options', [FournisseurController::class, 'options'])->name('fournisseurs.options');
    Route::apiResource('fournisseurs', FournisseurController::class)->except('show');
    Route::get('/articles/unites', [ArticleController::class, 'unites'])->name('articles.unites');
    Route::get('/articles/{article}/stock', [ArticleController::class, 'stockDetail'])->name('articles.stock');
    Route::apiResource('articles', ArticleController::class)->except('show');
});

/* Entrées de stock : en-têtes, lots, validation, import Excel */
Route::middleware('can:pharma.entrees')->group(function () {
    Route::apiResource('entrees', EntreeIndexController::class)->except('show')->parameters(['entrees' => 'entreeIndex']);
    Route::get('/entrees/{entreeIndex}/lots', [EntreeDetailController::class, 'index'])->name('entrees.lots.index');
    Route::post('/entrees/{entreeIndex}/lots', [EntreeDetailController::class, 'store'])->name('entrees.lots.store');
    Route::post('/entrees/{entreeIndex}/import', [EntreeDetailController::class, 'import'])->name('entrees.import');
    Route::put('/entree-lots/{entreeDetail}', [EntreeDetailController::class, 'update'])->name('entree-lots.update');
    Route::post('/entree-lots/{entreeDetail}/valider', [EntreeDetailController::class, 'valider'])->name('entree-lots.valider');
    Route::post('/entree-lots/{entreeDetail}/annuler-validation', [EntreeDetailController::class, 'annulerValidation'])->name('entree-lots.annuler-validation');
    Route::delete('/entree-lots/{entreeDetail}', [EntreeDetailController::class, 'destroy'])->name('entree-lots.destroy');
});

/* Articles actifs avec leur stock : sélecteur des entrées et des sorties (contrôle d'accès dans le contrôleur) */
Route::get('/stock/articles', [EntreeDetailController::class, 'articles'])->name('stock.articles');

/* Sorties de stock : pertes, casse, péremption, ajustements */
Route::middleware('can:pharma.sorties')->group(function () {
    Route::get('/sortie-lots', [SortieDetailController::class, 'lots'])->name('sortie-lots.index');
    Route::apiResource('sorties', SortieIndexController::class)->except('show')->parameters(['sorties' => 'sortieIndex']);
    Route::get('/sorties/{sortieIndex}/lignes', [SortieDetailController::class, 'index'])->name('sorties.lignes.index');
    Route::post('/sorties/{sortieIndex}/lignes', [SortieDetailController::class, 'store'])->name('sorties.lignes.store');
    Route::delete('/sortie-lignes/{sortieDetail}', [SortieDetailController::class, 'destroy'])->name('sortie-lignes.destroy');
});

/* Journal des mouvements de stock */
Route::get('/mouvements', [MouvementController::class, 'index'])->middleware('can:pharma.mouvements')->name('mouvements.index');

/* Inventaires physiques */
Route::middleware('can:pharma.inventaire')->group(function () {
    Route::apiResource('inventaires', InventaireController::class)->except('update');
    Route::post('/inventaires/{inventaire}/valider', [InventaireController::class, 'valider'])->name('inventaires.valider');
    Route::post('/inventaires/{inventaire}/appliquer', [InventaireController::class, 'appliquer'])->name('inventaires.appliquer');
    Route::put('/inventaire-lignes/{inventaireDetail}', [InventaireController::class, 'updateLigne'])->name('inventaire-lignes.update');
});

/* Réapprovisionnement et consommation moyenne mensuelle */
Route::middleware('can:pharma.reappro')->group(function () {
    Route::get('/reappro', [ReapproController::class, 'index'])->name('reappro.index');
    Route::get('/reappro/export', [ReapproController::class, 'export'])->name('reappro.export');
});
Route::get('/cmm', [CmmController::class, 'index'])->middleware('can:pharma.cmm')->name('cmm.index');

/* Caisse : sessions, panier (stock réservé dès l'ajout), encaissement */
Route::middleware('can:pharma.caisse')->prefix('caisse')->name('caisse.')->group(function () {
    Route::get('/session', [CaisseSessionController::class, 'show'])->name('session');
    Route::post('/ouvrir', [CaisseSessionController::class, 'ouvrir'])->name('ouvrir');
    Route::post('/fermer', [CaisseSessionController::class, 'fermer'])->name('fermer');
    Route::get('/articles', [PanierController::class, 'articles'])->name('articles');
    Route::get('/panier', [PanierController::class, 'index'])->name('panier.index');
    Route::post('/panier', [PanierController::class, 'store'])->name('panier.store');
    Route::delete('/panier', [PanierController::class, 'clear'])->name('panier.clear');
    Route::delete('/panier/{panier}', [PanierController::class, 'destroy'])->name('panier.destroy');
    Route::post('/ventes', [VenteController::class, 'store'])->name('ventes.store');
    Route::get('/ordres-soins', [OrdreCaisseController::class, 'index'])->name('ordres-soins.index');
    Route::post('/ordres-soins/{ordreSoins}/panier', [OrdreCaisseController::class, 'ajouterAuPanier'])->name('ordres-soins.panier');
});

/* Reçu d'une vente : lisible depuis la caisse comme depuis l'historique (contrôle d'accès dans le contrôleur) */
Route::get('/ventes/{vente}/recu', [VenteController::class, 'recu'])->name('ventes.recu');

/* Historique des ventes */
Route::middleware('can:pharma.historique')->group(function () {
    Route::get('/ventes', [HistoriqueVenteController::class, 'index'])->name('ventes.index');
    Route::get('/ventes/{vente}', [HistoriqueVenteController::class, 'show'])->name('ventes.show');
});

/* Tableau de bord */
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('can:pharma.dashboard')->name('dashboard');

/* Patients adressés par la caisse au médecin ou au dentiste */
Route::middleware('can:pharma.consultations')->group(function () {
    Route::get('/consultations', [ConsultationController::class, 'index'])->name('consultations.index');
    Route::post('/consultations/{consultation}/vu', [ConsultationController::class, 'vu'])->name('consultations.vu');
    Route::get('/actes-dentaires', [OrdreSoinsController::class, 'actes'])->name('actes-dentaires.index');
    Route::post('/consultations/{consultation}/ordre-soins', [OrdreSoinsController::class, 'store'])->name('consultations.ordre-soins.store');
    Route::delete('/ordres-soins/{ordreSoins}', [OrdreSoinsController::class, 'destroy'])->name('ordres-soins.destroy');
});

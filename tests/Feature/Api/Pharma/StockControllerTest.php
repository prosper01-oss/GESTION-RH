<?php

namespace Tests\Feature\Api\Pharma;

use App\Enums\RoleName;
use App\Imports\Pharma\EntreeDetailImport;
use App\Models\Pharma\Article;
use App\Models\Pharma\EntreeDetail;
use App\Models\Pharma\Inventaire;
use App\Models\Pharma\Unite;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StockControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $pharmacien;

    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pharmacien = $this->userWithRole(RoleName::Pharmacien);
        $unite = Unite::create(['nomUnite' => 'BT', 'nomComplet' => 'Boîte', 'supun' => false]);
        $this->article = Article::create(['designation' => 'Paracétamol', 'presentation' => 10, 'unite' => $unite->id, 'seuil' => 20]);
    }

    public function test_a_lot_is_priced_with_the_margin_and_integrated_into_stock_on_validation(): void
    {
        $lot = $this->lotValide(qte: 100, prixBoite: 1000);

        // PU achat unitaire 100 → PU de vente 120 (marge de 20 % par défaut)
        $this->assertEqualsWithDelta(120.0, $lot->prix_unitaire, 0.001);
        $this->assertMatchesRegularExpression('#^LOT-A-0001-\d{2}/\d{2}/\d{2}$#', $lot->lot);
        $this->assertSame(100, $this->article->fresh()->stock);
        $this->assertDatabaseHas('pharma_stock_mouvements', ['type' => 'entree', 'qte' => 100, 'stock_avant' => 0, 'stock_apres' => 100]);

        $this->actingAs($this->pharmacien)->putJson("/api/v1/pharma/entree-lots/{$lot->id}", $this->lotPayload(50, 1000))
            ->assertUnprocessable()->assertJsonPath('message', 'Un lot validé ou vendu ne peut plus être modifié.');
        $this->actingAs($this->pharmacien)->deleteJson("/api/v1/pharma/entree-lots/{$lot->id}")
            ->assertUnprocessable()->assertJsonPath('message', 'Impossible de supprimer un lot validé.');
    }

    public function test_validation_can_be_cancelled_only_while_the_lot_is_untouched(): void
    {
        $lot = $this->lotValide(qte: 100, prixBoite: 1000);

        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/entree-lots/{$lot->id}/annuler-validation")->assertOk();
        $this->assertSame(0, $this->article->fresh()->stock);

        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/entree-lots/{$lot->id}/valider")->assertOk();
        $sortie = $this->sortie();
        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/sorties/{$sortie}/lignes", ['entree_detail_id' => $lot->id, 'qte_sortie' => 5])->assertCreated();

        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/entree-lots/{$lot->id}/annuler-validation")
            ->assertUnprocessable()->assertJsonPath('message', 'Impossible : ce lot a déjà été partiellement consommé (ventes ou sorties).');
    }

    public function test_an_expired_lot_cannot_be_validated(): void
    {
        $index = $this->entree();
        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/entrees/{$index}/lots", $this->lotPayload(10, 500, now()->subDay()->toDateString()))->assertCreated();
        $lot = EntreeDetail::firstOrFail();

        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/entree-lots/{$lot->id}/valider")
            ->assertUnprocessable()->assertJsonPath('message', 'Cet article est périmé !');
        $this->assertSame(0, $this->article->fresh()->stock);
    }

    public function test_an_exit_decrements_stock_values_the_loss_and_can_be_restored(): void
    {
        $lot = $this->lotValide(qte: 100, prixBoite: 1000);
        $sortie = $this->sortie();

        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/sorties/{$sortie}/lignes", ['entree_detail_id' => $lot->id, 'qte_sortie' => 150])
            ->assertUnprocessable()->assertJsonPath('message', 'Quantité insuffisante sur ce lot : reste 100 unité(s).');

        $ligne = $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/sorties/{$sortie}/lignes", ['entree_detail_id' => $lot->id, 'qte_sortie' => 30])
            ->assertCreated()->json('id');

        $this->assertSame(70, $this->article->fresh()->stock);
        $this->assertSame(70, $lot->fresh()->stock_restant_lot);
        // 1000 / 10 = 100 par unité × 30
        $this->actingAs($this->pharmacien)->getJson("/api/v1/pharma/sorties/{$sortie}/lignes")->assertOk()->assertJsonPath('data.0.montant_perte', 3000);

        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/sorties/{$sortie}/lignes", ['entree_detail_id' => $lot->id, 'qte_sortie' => 1])
            ->assertUnprocessable()->assertJsonPath('message', 'Ce lot figure déjà dans cette sortie.');

        $this->actingAs($this->pharmacien)->deleteJson("/api/v1/pharma/sortie-lignes/{$ligne}")->assertOk();
        $this->assertSame(100, $this->article->fresh()->stock);
        $this->assertDatabaseHas('pharma_stock_mouvements', ['type' => 'sortie_restauration', 'qte' => 30]);
    }

    public function test_a_fully_consumed_lot_is_flagged_as_sold(): void
    {
        $lot = $this->lotValide(qte: 10, prixBoite: 1000);
        $sortie = $this->sortie();

        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/sorties/{$sortie}/lignes", ['entree_detail_id' => $lot->id, 'qte_sortie' => 10])->assertCreated();

        $this->assertTrue($lot->fresh()->isVendus);
        $this->assertSame(0, $this->article->fresh()->stock);
    }

    public function test_inventory_applies_negative_and_positive_gaps(): void
    {
        $this->lotValide(qte: 100, prixBoite: 1000);

        $id = $this->actingAs($this->pharmacien)->postJson('/api/v1/pharma/inventaires', ['date_inventaire' => now()->toDateString()])
            ->assertCreated()->json('id');

        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/inventaires/{$id}/valider")
            ->assertUnprocessable()->assertJsonPath('message', "Aucun article n'est encore compté.");

        $ligne = $this->actingAs($this->pharmacien)->getJson("/api/v1/pharma/inventaires/{$id}")->json('data.0.id');
        $this->actingAs($this->pharmacien)->putJson("/api/v1/pharma/inventaire-lignes/{$ligne}", ['compte' => 80])->assertOk();
        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/inventaires/{$id}/valider")->assertOk();
        $this->actingAs($this->pharmacien)->putJson("/api/v1/pharma/inventaire-lignes/{$ligne}", ['compte' => 90])
            ->assertUnprocessable()->assertJsonPath('message', "La saisie n'est possible que sur un brouillon.");

        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/inventaires/{$id}/appliquer")->assertOk();
        $this->assertSame(80, $this->article->fresh()->stock);
        $this->assertSame(Inventaire::STATUT_APPLIQUE, Inventaire::findOrFail($id)->statut);
        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/inventaires/{$id}/appliquer")->assertUnprocessable();

        // Écart positif : un lot d'inventaire est créé et intégré au stock.
        $second = $this->actingAs($this->pharmacien)->postJson('/api/v1/pharma/inventaires', ['date_inventaire' => now()->toDateString()])->json('id');
        $ligne2 = $this->actingAs($this->pharmacien)->getJson("/api/v1/pharma/inventaires/{$second}")->json('data.0.id');
        $this->actingAs($this->pharmacien)->putJson("/api/v1/pharma/inventaire-lignes/{$ligne2}", ['compte' => 95])->assertOk();
        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/inventaires/{$second}/valider")->assertOk();
        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/inventaires/{$second}/appliquer")->assertOk();

        $this->assertSame(95, $this->article->fresh()->stock);
        $this->assertSame(2, EntreeDetail::where('article_id', $this->article->id)->where('isValide', true)->count());
    }

    public function test_a_failed_inventory_application_changes_nothing(): void
    {
        $lot = $this->lotValide(qte: 100, prixBoite: 1000);
        $id = $this->actingAs($this->pharmacien)->postJson('/api/v1/pharma/inventaires', ['date_inventaire' => now()->toDateString()])->json('id');
        $ligne = $this->actingAs($this->pharmacien)->getJson("/api/v1/pharma/inventaires/{$id}")->json('data.0.id');
        $this->actingAs($this->pharmacien)->putJson("/api/v1/pharma/inventaire-lignes/{$ligne}", ['compte' => 20])->assertOk();
        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/inventaires/{$id}/valider")->assertOk();

        // Le stock réel descend à 10 alors que le lot en contient encore 100 : l'écart négatif ne peut pas être absorbé.
        $lot->update(['stock_restant_lot' => 10]);

        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/inventaires/{$id}/appliquer")->assertUnprocessable();
        $this->assertSame(100, $this->article->fresh()->stock);
        $this->assertSame(10, $lot->fresh()->stock_restant_lot);
        $this->assertSame(Inventaire::STATUT_VALIDE, Inventaire::findOrFail($id)->statut);
    }

    public function test_excel_rows_become_read_only_lots(): void
    {
        $index = $this->entree();
        $import = new EntreeDetailImport($index);

        $import->collection(collect([
            ['En-tête 1'],
            ['En-tête 2'],
            ['REF-'.str_pad((string) $this->article->id, 4, '0', STR_PAD_LEFT), '', '', '', '', '2/20', '', '', '12 000 Ar', '', '', 'Mars 2030'],
            ['REF-9999', '', '', '', '', '1/10', '', '', '1000'],
            ['REF-'.str_pad((string) $this->article->id, 4, '0', STR_PAD_LEFT), '', '', '', '', '0/0', '', '', '1000'],
        ]));

        $this->assertSame(1, $import->importes());
        $this->assertCount(2, $import->lignesIgnorees());

        $lot = EntreeDetail::firstOrFail();
        $this->assertSame('import', $lot->source);
        $this->assertSame(20, $lot->qte_entree);
        $this->assertSame('2030-03-01', $lot->date_peremption->toDateString());

        $this->actingAs($this->pharmacien)->putJson("/api/v1/pharma/entree-lots/{$lot->id}", $this->lotPayload(5, 100))
            ->assertUnprocessable()->assertJsonPath('message', 'Un lot importé ne peut pas être modifié.');
        $this->actingAs($this->pharmacien)->deleteJson("/api/v1/pharma/entree-lots/{$lot->id}")
            ->assertUnprocessable()->assertJsonPath('message', 'Un lot importé ne peut pas être supprimé.');
        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/entree-lots/{$lot->id}/valider")->assertOk();
        $this->assertSame(20, $this->article->fresh()->stock);
    }

    public function test_an_excel_file_is_imported_through_the_api(): void
    {
        $index = $this->entree();
        $reference = 'REF-'.str_pad((string) $this->article->id, 4, '0', STR_PAD_LEFT);

        $feuille = new Spreadsheet;
        $feuille->getActiveSheet()
            ->fromArray([['Bon de commande'], ['Réf.', '', '', '', '', 'Qté', '', '', 'Prix']], null, 'A1')
            ->fromArray([$reference, '', '', '', '', '2/20', '', '', '12 000 Ar', '', '', 'Mars 2030'], null, 'A3')
            ->fromArray(['REF-9999', '', '', '', '', '1/10', '', '', '500'], null, 'A4');
        $chemin = tempnam(sys_get_temp_dir(), 'omit-import').'.xlsx';
        (new Xlsx($feuille))->save($chemin);

        $this->actingAs($this->pharmacien)
            ->post("/api/v1/pharma/entrees/{$index}/import", ['file' => new UploadedFile($chemin, 'commande.xlsx', null, null, true)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('importes', 1)
            ->assertJsonPath('ignorees.0.motif', 'Référence inconnue : REF-9999');

        $this->assertSame(1, EntreeDetail::where('entree_indices_id', $index)->where('source', 'import')->count());
        $this->assertDatabaseHas('pharma_entree_indices', ['id' => $index, 'nb_article' => 1]);
        @unlink($chemin);
    }

    public function test_reappro_suggests_articles_under_their_threshold(): void
    {
        $this->article->update(['stock' => 5, 'qte_max' => 60]);

        $this->actingAs($this->pharmacien)->getJson('/api/v1/pharma/reappro')->assertOk()
            ->assertJsonPath('data.0.designation', 'Paracétamol')
            ->assertJsonPath('data.0.manquant', 55)
            ->assertJsonPath('data.0.base', 'cible');

        $this->actingAs($this->pharmacien)->get('/api/v1/pharma/reappro/export?fournisseur=Pharmalagasy')->assertOk();
    }

    public function test_exits_and_inventory_are_reserved_to_their_modules(): void
    {
        $caissier = $this->userWithRole(RoleName::Caissier);

        $this->actingAs($caissier)->getJson('/api/v1/pharma/inventaires')->assertForbidden();
        $this->actingAs($caissier)->getJson('/api/v1/pharma/sorties')->assertForbidden();
        $this->actingAs($caissier)->getJson('/api/v1/pharma/mouvements')->assertForbidden();
        $this->actingAs($caissier)->getJson('/api/v1/pharma/stock/articles')->assertForbidden();
        $this->actingAs($this->pharmacien)->getJson('/api/v1/pharma/stock/articles')->assertOk();
    }

    private function entree(): int
    {
        return $this->actingAs($this->pharmacien)->postJson('/api/v1/pharma/entrees', ['ref_entree' => 'ENT-'.fake()->unique()->numerify('###')])
            ->assertCreated()->json('id');
    }

    private function sortie(): int
    {
        return $this->actingAs($this->pharmacien)->postJson('/api/v1/pharma/sorties', ['ref_sortie' => 'SOR-'.fake()->unique()->numerify('###'), 'motif' => 'perte'])
            ->assertCreated()->json('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function lotPayload(int $qte, int $prixBoite, ?string $peremption = null): array
    {
        return ['article_id' => $this->article->id, 'qte_entree' => $qte, 'prix_achat_boite' => $prixBoite, 'date_peremption' => $peremption];
    }

    private function lotValide(int $qte, int $prixBoite): EntreeDetail
    {
        $index = $this->entree();
        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/entrees/{$index}/lots", $this->lotPayload($qte, $prixBoite))->assertCreated();
        $lot = EntreeDetail::latest('id')->firstOrFail();
        $this->actingAs($this->pharmacien)->postJson("/api/v1/pharma/entree-lots/{$lot->id}/valider")->assertOk();

        return $lot->fresh();
    }
}

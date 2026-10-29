<?php

namespace Tests\Feature\Api\Pharma;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\Pharma\Analyse;
use App\Models\Pharma\Article;
use App\Models\Pharma\Categorie;
use App\Models\Pharma\EntreeDetail;
use App\Models\Pharma\EntreeIndex;
use App\Models\Pharma\Kit;
use App\Models\Pharma\Panier;
use App\Models\Pharma\Service;
use App\Models\Pharma\Unite;
use App\Models\Pharma\Vente;
use App\Models\User;
use App\Services\Pharma\Montant;
use App\Services\Pharma\RecuVente;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CaisseControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $caissier;

    private Article $article;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caissier = $this->userWithRole(RoleName::Caissier);
        $unite = Unite::create(['nomUnite' => 'BT', 'nomComplet' => 'Boîte', 'supun' => false]);
        $this->article = Article::create(['designation' => 'Paracétamol', 'presentation' => 10, 'unite' => $unite->id, 'seuil' => 20, 'stock' => 100]);

        $index = EntreeIndex::create(['ref_entree' => 'ENT-1']);
        // Lot qui périme bientôt (60 unités à 120 Ar) puis lot plus lointain (40 unités à 150 Ar)
        $this->lot($index, 'LOT-PROCHE', 60, 120, now()->addMonths(2));
        $this->lot($index, 'LOT-LOIN', 40, 150, now()->addYear());
    }

    public function test_nothing_can_be_sold_while_the_register_is_closed(): void
    {
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', ['page_type' => 'article', 'article_id' => $this->article->id, 'qte' => 10])->assertCreated();

        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ventes', ['client' => 'Rabe', 'montant_paye' => 5000])
            ->assertForbidden()->assertJsonPath('message', "La caisse doit être ouverte avant d'encaisser.");
    }

    public function test_medicines_are_reserved_lot_by_lot_with_the_nearest_expiry_first(): void
    {
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', ['page_type' => 'article', 'article_id' => $this->article->id, 'qte' => 80])->assertCreated();

        $this->assertSame(20, $this->article->fresh()->stock);
        $lignes = Panier::orderBy('id')->get();
        $this->assertCount(2, $lignes);
        $this->assertSame([60, 20], $lignes->pluck('qte')->all());
        // 60 × 120 + 20 × 150
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/caisse/panier')->assertOk()->assertJsonPath('total', 10200);

        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', ['page_type' => 'article', 'article_id' => $this->article->id, 'qte' => 5])
            ->assertUnprocessable()->assertJsonPath('code', 'deja_ajoutee');

        $this->actingAs($this->caissier)->deleteJson('/api/v1/pharma/caisse/panier')->assertOk();
        $this->assertSame(100, $this->article->fresh()->stock);
        $this->assertSame(0, Panier::count());
        $this->assertSame(60, EntreeDetail::where('lot', 'LOT-PROCHE')->value('stock_restant_lot'));
    }

    public function test_expired_lots_are_never_sold(): void
    {
        EntreeDetail::where('lot', 'LOT-PROCHE')->update(['date_peremption' => now()->subDay()]);

        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', ['page_type' => 'article', 'article_id' => $this->article->id, 'qte' => 50])
            ->assertUnprocessable()->assertJsonPath('code', 'insuffisant');
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/caisse/articles')->assertOk()->assertJsonPath('data.0.stock_vendable', 40);
    }

    public function test_a_sale_requires_enough_money_and_keeps_the_lot_trace(): void
    {
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ouvrir', ['fond_de_caisse' => 10000])->assertCreated();
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ouvrir', ['fond_de_caisse' => 5])->assertStatus(409);
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', ['page_type' => 'article', 'article_id' => $this->article->id, 'qte' => 10])->assertCreated();

        // 10 × 120 = 1 200 Ar
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ventes', ['client' => 'Rabe', 'montant_paye' => 1000])
            ->assertUnprocessable()->assertJsonPath('code', 'insuffisant');
        $this->assertSame(1, Panier::count());

        $reponse = $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ventes', ['client' => 'Rabe', 'montant_paye' => 1330])
            ->assertCreated()
            ->assertJsonPath('data.total', 1200)
            ->assertJsonPath('data.monnaie', 130)
            ->assertJsonPath('data.monnaie_arrondie', 100);

        $this->assertSame(0, Panier::count());
        $this->assertSame(90, $this->article->fresh()->stock, 'Le stock est réservé à l’ajout au panier, la vente n’y touche pas.');
        $vente = Vente::firstOrFail();
        $this->assertSame($reponse->json('data.reference'), $vente->reference_vente);
        $this->assertNotNull($vente->details()->first()->entree_detail_id);
        $this->assertEqualsWithDelta(1200.0, (float) $vente->details()->sum('montant_paye'), 0.01);

        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/caisse/session')->assertOk()
            ->assertJsonPath('data.nb_ventes', 1)->assertJsonPath('data.theorique', 11200);

        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/fermer', ['montant_reel' => 11000, 'observations' => 'Manque 200'])->assertOk();
        $this->assertDatabaseHas('pharma_caisse_sessions', ['statut' => 'fermee', 'theorique' => 11200, 'montant_reel' => 11000, 'ecart' => -200]);
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/caisse/session')->assertJsonPath('data', null);
    }

    public function test_consultation_services_analyses_and_kits_are_priced_by_the_server(): void
    {
        $categorie = Categorie::create(['nom_cat' => 'Soins']);
        Service::create(['categorie_id' => $categorie->id, 'nom_service' => 'Consultation générale', 'prix_service' => 5000, 'isConsult' => 1]);
        $radio = Service::create(['categorie_id' => $categorie->id, 'nom_service' => 'Radio', 'prix_service' => 20000, 'isConsult' => 0]);
        $docteur = Employee::factory()->create(['nom' => 'RAKOTO', 'prenom' => 'Jean', 'position' => 'Docteur']);
        $a1 = Analyse::create(['nom' => 'NFS', 'prix' => 8000]);
        $a2 = Analyse::create(['nom' => 'Glycémie', 'prix' => 3000]);
        $kit = Kit::create(['nom_kit' => 'Kit pansement', 'prix_kit' => 2500]);
        $url = '/api/v1/pharma/caisse/panier';

        $this->actingAs($this->caissier)->postJson($url, ['page_type' => 'consultation', 'nom_patient' => 'Rabe', 'sex_patient' => 1, 'age_patient' => 30, 'unite_age' => 1, 'type_docteur' => 'dentiste', 'docteur' => $docteur->id])
            ->assertUnprocessable()->assertJsonPath('message', 'Le docteur sélectionné est introuvable pour ce type.');
        $this->actingAs($this->caissier)->postJson($url, ['page_type' => 'consultation', 'nom_patient' => 'Rabe', 'sex_patient' => 1, 'age_patient' => 30, 'unite_age' => 1, 'type_docteur' => 'generaliste', 'docteur' => $docteur->id, 'montant_brut' => 1])->assertCreated();
        $this->actingAs($this->caissier)->postJson($url, ['page_type' => 'service', 'service_id' => $radio->id, 'qte' => 2])->assertCreated();
        $this->actingAs($this->caissier)->postJson($url, ['page_type' => 'analyse', 'analyse_id' => [$a1->id, $a2->id]])->assertCreated();
        $this->actingAs($this->caissier)->postJson($url, ['page_type' => 'analyse', 'analyse_id' => [$a1->id]])->assertUnprocessable()->assertJsonPath('code', 'deja_ajoutee');
        $this->actingAs($this->caissier)->postJson($url, ['page_type' => 'kit', 'kit_id' => $kit->id, 'qte' => 3])->assertCreated();

        // 5 000 + 2 × 20 000 + (8 000 + 3 000) + 3 × 2 500
        $this->actingAs($this->caissier)->getJson($url)->assertOk()->assertJsonPath('total', 63500)->assertJsonPath('patient', 'Rabe');

        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ouvrir', ['fond_de_caisse' => 0])->assertCreated();
        $id = $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ventes', ['client' => 'Rabe', 'montant_paye' => 65000])->assertCreated()->json('data.id');

        $this->assertDatabaseCount('pharma_consultations', 1);
        $this->actingAs($this->caissier)->get("/api/v1/pharma/ventes/{$id}/recu")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_doctors_are_listed_automatically_from_the_staff_function(): void
    {
        $docteur = Employee::factory()->create(['nom' => 'RAKOTO', 'prenom' => 'Jean', 'position' => 'Docteur']);
        $medecin = Employee::factory()->create(['nom' => 'ANDRY', 'prenom' => 'Lalao', 'position' => 'Médecin']);
        $dentiste = Employee::factory()->create(['nom' => 'HERY', 'prenom' => 'Zo', 'position' => 'Chirurgien Dentiste']);
        $faute = Employee::factory()->create(['nom' => 'RAJAO', 'prenom' => 'Tahin', 'position' => 'Dentise']);
        $stomato = Employee::factory()->create(['nom' => 'STOMA', 'prenom' => 'Paul', 'position' => 'Médecin Stomato']);
        Employee::factory()->create(['position' => 'Assistant dentisterie']);
        Employee::factory()->create(['position' => 'Technicien Stomatologie']);
        $infirmier = Employee::factory()->create(['position' => 'Infirmier']);
        Employee::factory()->create(['nom' => 'ZZ', 'position' => 'Médecin-Chef', 'status' => 'inactif']);

        // Les types de consultation restent « Généraliste » et « Dentiste ».
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/services/options')->assertOk()
            ->assertJsonPath('types_docteur.0.libelle', 'Généraliste')
            ->assertJsonPath('types_docteur.1.libelle', 'Dentiste');

        // Généraliste : tous les docteurs qui ne sont pas dentistes.
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/docteurs?type_docteur=generaliste')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $medecin->id)->assertJsonPath('data.1.id', $docteur->id);
        // Dentiste : les dentistes, y compris « Dentise » (faute de frappe) et « Médecin Stomato ».
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/docteurs?type_docteur=dentiste')->assertOk()
            ->assertJsonCount(3, 'data')->assertJsonPath('data.0.id', $dentiste->id)
            ->assertJsonFragment(['id' => $faute->id])->assertJsonFragment(['id' => $stomato->id]);
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/docteurs')->assertOk()->assertJsonCount(5, 'data');

        // Le panier n'accepte que ces docteurs, avec le bon type.
        $categorie = Categorie::create(['nom_cat' => 'Soins']);
        Service::create(['categorie_id' => $categorie->id, 'nom_service' => 'Consultation', 'prix_service' => 5000, 'isConsult' => 1]);
        $patient = ['page_type' => 'consultation', 'nom_patient' => 'Rabe', 'sex_patient' => 1, 'age_patient' => 30, 'unite_age' => 1];

        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', $patient + ['type_docteur' => 'generaliste', 'docteur' => $infirmier->id])
            ->assertUnprocessable()->assertJsonPath('message', 'Le docteur sélectionné est introuvable pour ce type.');
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', $patient + ['type_docteur' => 'generaliste', 'docteur' => $dentiste->id])
            ->assertUnprocessable();
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', $patient + ['type_docteur' => 'orthodontiste', 'docteur' => $dentiste->id])
            ->assertUnprocessable()->assertJsonValidationErrors('type_docteur');
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', $patient + ['type_docteur' => 'dentiste', 'docteur' => $faute->id])
            ->assertCreated();
        $this->assertSame($faute->id, (int) Panier::firstOrFail()->docteur);
    }

    public function test_a_consultation_without_a_configured_service_explains_how_to_fix_it(): void
    {
        $docteur = Employee::factory()->create(['position' => 'Docteur']);

        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', [
            'page_type' => 'consultation', 'nom_patient' => 'Rabe', 'sex_patient' => 1, 'age_patient' => 30, 'unite_age' => 1,
            'type_docteur' => 'generaliste', 'docteur' => $docteur->id,
        ])->assertUnprocessable()->assertJsonPath('message', 'Aucun service de consultation configuré. Créez-le dans Gestion Pharmacie > Services en cochant « Service de consultation ».');
    }

    public function test_services_are_split_on_the_ticket_by_category_with_the_category_as_title(): void
    {
        $imagerie = Categorie::create(['nom_cat' => 'Imagerie']);
        $dentisterie = Categorie::create(['nom_cat' => 'Dentisterie']);
        $radio = Service::create(['categorie_id' => $imagerie->id, 'nom_service' => 'Radiographie', 'prix_service' => 40000]);
        $carie = Service::create(['categorie_id' => $dentisterie->id, 'nom_service' => 'Soin de carie', 'prix_service' => 30000]);
        $url = '/api/v1/pharma/caisse/panier';

        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ouvrir', ['fond_de_caisse' => 0])->assertCreated();
        $this->actingAs($this->caissier)->postJson($url, ['page_type' => 'service', 'service_id' => $radio->id, 'qte' => 1])->assertCreated();
        $this->actingAs($this->caissier)->postJson($url, ['page_type' => 'service', 'service_id' => $carie->id, 'qte' => 2])->assertCreated();
        $this->actingAs($this->caissier)->postJson($url, ['page_type' => 'article', 'article_id' => $this->article->id, 'qte' => 10])->assertCreated();
        $reponse = $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ventes', ['client' => 'Rabe', 'montant_paye' => 101200])->assertCreated();

        $vente = Vente::findOrFail($reponse->json('data.id'));
        $sections = app(RecuVente::class)->sections($vente->details()->with('service.categorie')->get());

        $this->assertSame(['DENTISTERIE', 'IMAGERIE', 'MÉDICAMENTS'], array_column($sections, 'titre'));
        $this->assertSame(['60000.00', '40000.00', '1200.00'], array_column($sections, 'sous_total'));

        // Un seul ticket (une seule page) regroupe toutes les catégories.
        $pdf = $this->actingAs($this->caissier)->get("/api/v1/pharma/ventes/{$vente->id}/recu")->assertOk()->assertHeader('content-type', 'application/pdf')->getContent();
        $this->assertSame(1, preg_match_all('#/Type\s*/Page\b#', $pdf));
    }

    public function test_the_register_user_only_reaches_their_own_sales(): void
    {
        $autre = $this->userWithRole(RoleName::Caissier);
        $admin = $this->userWithRole(RoleName::Administrateur);
        $vente = Vente::create(['reference_vente' => 'V1', 'user_id' => $autre->id, 'client' => 'X', 'montant_brut' => 100, 'montant_paye' => 100, 'monnaie' => 0, 'mode_paiement' => 'cash']);
        $parametres = ['date_debut' => now()->subDay()->toDateString(), 'date_fin' => now()->toDateString()];

        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/ventes?'.http_build_query($parametres))->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->caissier)->getJson("/api/v1/pharma/ventes/{$vente->id}")->assertForbidden();
        $this->actingAs($this->caissier)->get("/api/v1/pharma/ventes/{$vente->id}/recu")->assertForbidden();

        $this->actingAs($admin)->getJson('/api/v1/pharma/ventes?'.http_build_query($parametres))->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->getJson("/api/v1/pharma/ventes/{$vente->id}")->assertOk()->assertJsonPath('vente.reference', 'V1');
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/ventes')->assertUnprocessable();
    }

    public function test_abandoned_baskets_give_their_stock_back(): void
    {
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', ['page_type' => 'article', 'article_id' => $this->article->id, 'qte' => 30])->assertCreated();
        $this->assertSame(70, $this->article->fresh()->stock);

        $this->artisan('omit:pharma-purger-paniers')->assertSuccessful();
        $this->assertSame(70, $this->article->fresh()->stock, 'Un panier récent est conservé.');

        $this->travel(config('pharma.panier_expiration_minutes') + 1)->minutes();
        $this->artisan('omit:pharma-purger-paniers')->assertSuccessful();

        $this->assertSame(100, $this->article->fresh()->stock);
        $this->assertSame(0, Panier::count());
        $this->assertDatabaseHas('pharma_stock_mouvements', ['type' => 'panier_retrait', 'qte' => 30]);
    }

    public function test_dashboard_summarises_sales_stock_alerts_and_expiries(): void
    {
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ouvrir', ['fond_de_caisse' => 0])->assertCreated();
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/panier', ['page_type' => 'article', 'article_id' => $this->article->id, 'qte' => 10])->assertCreated();
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ventes', ['client' => 'Rabe', 'montant_paye' => 1200])->assertCreated();
        EntreeDetail::where('lot', 'LOT-PROCHE')->update(['date_peremption' => now()->addDays(10)]);
        $this->article->update(['seuil' => 100]);

        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/dashboard')->assertOk()
            ->assertJsonPath('ca', 1200)
            ->assertJsonPath('nb_ventes', 1)
            ->assertJsonPath('panier_moyen', 1200)
            ->assertJsonPath('repartition.Médicament', 1200)
            ->assertJsonPath('top_articles.0.qte', 10)
            ->assertJsonPath('nb_ruptures', 1)
            ->assertJsonPath('peremption30j.0.lot', 'LOT-PROCHE')
            ->assertJsonPath('dernieres_ventes.0.client', 'Rabe');

        $this->actingAs($this->userWithRole(RoleName::Personnel))->getJson('/api/v1/pharma/dashboard')->assertForbidden();
    }

    public function test_change_is_rounded_to_the_nearest_hundred(): void
    {
        $this->assertSame(100.0, Montant::arrondirCentaine(130));
        $this->assertSame(100.0, Montant::arrondirCentaine(150));
        $this->assertSame(200.0, Montant::arrondirCentaine(160));
        $this->assertSame(0.0, Montant::arrondirCentaine(40));
        $this->assertSame(0.0, Montant::arrondirCentaine(-5));
    }

    private function lot(EntreeIndex $index, string $nom, int $qte, float $prixVente, $peremption): EntreeDetail
    {
        return EntreeDetail::create([
            'entree_indices_id' => $index->id, 'article_id' => $this->article->id, 'lot' => $nom,
            'qte_initial' => 0, 'qte_entree' => $qte, 'stock_restant_lot' => $qte, 'stock_dispo' => $qte,
            'prix_achat_boite' => 1000, 'prix_unitaire' => $prixVente, 'montant_entree' => 0, 'montant_gain_brut' => 0,
            'date_peremption' => $peremption, 'isValide' => true,
        ]);
    }
}

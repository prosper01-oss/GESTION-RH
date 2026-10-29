<?php

namespace Tests\Feature\Api\Pharma;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Pharma\Analyse;
use App\Models\Pharma\Article;
use App\Models\Pharma\Categorie;
use App\Models\Pharma\Fournisseur;
use App\Models\Pharma\Kit;
use App\Models\Pharma\Unite;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CatalogueControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_manages_analyses_and_names_stay_unique(): void
    {
        $admin = $this->userWithRole(RoleName::Administrateur);

        $this->actingAs($admin)->postJson('/api/v1/pharma/analyses', ['nom' => 'NFS', 'prix' => 8000])->assertCreated();
        $this->actingAs($admin)->postJson('/api/v1/pharma/analyses', ['nom' => 'NFS', 'prix' => 9000])
            ->assertUnprocessable()->assertJsonPath('message', 'Une analyse porte déjà ce nom.');

        $analyse = Analyse::where('nom', 'NFS')->firstOrFail();
        $this->actingAs($admin)->putJson("/api/v1/pharma/analyses/{$analyse->id}", ['nom' => 'NFS', 'prix' => 8500])->assertOk();
        $this->assertSame(8500.0, $analyse->fresh()->prix);

        $this->actingAs($admin)->deleteJson("/api/v1/pharma/analyses/{$analyse->id}")->assertOk();
        $this->actingAs($admin)->getJson('/api/v1/pharma/analyses')->assertOk()->assertJsonCount(0, 'data');

        // Le nom d'une analyse supprimée logiquement peut être réutilisé.
        $this->actingAs($admin)->postJson('/api/v1/pharma/analyses', ['nom' => 'NFS', 'prix' => 9000])->assertCreated();
        $this->assertSame(1, Analyse::where('nom', 'NFS')->count());
        $this->assertTrue($analyse->fresh()->etat);
    }

    public function test_service_requires_an_existing_category(): void
    {
        $admin = $this->userWithRole(RoleName::Administrateur);
        $categorie = Categorie::create(['nom_cat' => 'Imagerie']);

        $this->actingAs($admin)->postJson('/api/v1/pharma/services', [
            'categorie_id' => 999, 'nom_service' => 'Radio', 'prix_service' => 15000,
        ])->assertUnprocessable()->assertJsonValidationErrors('categorie_id');

        $this->actingAs($admin)->postJson('/api/v1/pharma/services', [
            'categorie_id' => $categorie->id, 'nom_service' => 'Radio', 'prix_service' => 15000, 'isConsult' => false,
        ])->assertCreated();
    }

    public function test_kit_composition_rejects_the_same_article_twice(): void
    {
        $admin = $this->userWithRole(RoleName::Administrateur);
        $kit = Kit::create(['nom_kit' => 'Kit pansement', 'prix_kit' => 5000]);
        $article = $this->article();

        $payload = ['id_kit' => $kit->id, 'id_article' => $article->id, 'qte_kit' => 2];
        $this->actingAs($admin)->postJson('/api/v1/pharma/detail-kits', $payload)->assertCreated();
        $this->actingAs($admin)->postJson('/api/v1/pharma/detail-kits', $payload)
            ->assertUnprocessable()->assertJsonPath('message', 'Cet article est déjà présent dans ce kit.');
        $this->actingAs($admin)->postJson('/api/v1/pharma/detail-kits', ['qte_kit' => 0] + $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('qte_kit');
    }

    public function test_pharmacien_manages_articles_and_suppliers_but_not_the_catalogue(): void
    {
        $pharmacien = $this->userWithRole(RoleName::Pharmacien);
        $unite = Unite::create(['nomUnite' => 'BT', 'nomComplet' => 'Boîte', 'supun' => false]);

        $this->actingAs($pharmacien)->postJson('/api/v1/pharma/articles', [
            'designation' => 'Paracétamol 500', 'presentation' => 10, 'unite' => $unite->id, 'seuil' => 5,
        ])->assertUnprocessable()->assertJsonPath('errors.seuil.0', "Le seuil d'alerte ne doit pas être inférieur à la présentation.");

        $this->actingAs($pharmacien)->postJson('/api/v1/pharma/articles', [
            'designation' => 'Paracétamol 500', 'presentation' => 10, 'unite' => $unite->id, 'seuil' => 20,
        ])->assertCreated();

        $this->actingAs($pharmacien)->getJson('/api/v1/pharma/articles')->assertOk()
            ->assertJsonPath('data.0.alerte', 'rupture')
            ->assertJsonPath('data.0.peut_supprimer', true);

        $this->actingAs($pharmacien)->postJson('/api/v1/pharma/fournisseurs', ['nom' => 'Pharmalagasy'])->assertCreated();
        $this->actingAs($pharmacien)->postJson('/api/v1/pharma/fournisseurs', ['nom' => 'Pharmalagasy'])->assertUnprocessable();
        $this->assertSame(1, Fournisseur::count());

        $this->actingAs($pharmacien)->getJson('/api/v1/pharma/analyses')->assertForbidden();
        $this->actingAs($pharmacien)->postJson('/api/v1/pharma/kits', ['nom_kit' => 'X', 'prix_kit' => 1])->assertForbidden();
    }

    public function test_caissier_cannot_delete_articles_nor_open_the_stock_screens(): void
    {
        $caissier = $this->userWithRole(RoleName::Caissier);
        $article = $this->article();

        $this->actingAs($caissier)->getJson('/api/v1/pharma/articles')->assertForbidden();
        $this->actingAs($caissier)->deleteJson("/api/v1/pharma/articles/{$article->id}")->assertForbidden();
        // La caisse lit les sélecteurs du catalogue, la gestion complète lui reste fermée.
        $this->actingAs($caissier)->getJson('/api/v1/pharma/services/options')->assertOk();
        $this->actingAs($caissier)->getJson('/api/v1/pharma/analyses/options')->assertOk();
        $this->actingAs($caissier)->getJson('/api/v1/pharma/kits')->assertForbidden();
    }

    public function test_a_module_can_be_granted_to_a_single_account(): void
    {
        $caissier = $this->userWithRole(RoleName::Caissier);
        $this->actingAs($caissier)->getJson('/api/v1/pharma/articles')->assertForbidden();

        $caissier->directPermissions()->attach(Permission::where('name', 'pharma.articles')->value('id'));

        $this->actingAs(User::find($caissier->id))->getJson('/api/v1/pharma/articles')->assertOk();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/pharma/articles')->assertUnauthorized();
    }

    private function article(): Article
    {
        $unite = Unite::firstOrCreate(['nomUnite' => 'BT'], ['nomComplet' => 'Boîte', 'supun' => false]);

        return Article::create(['designation' => 'Gaze', 'presentation' => 1, 'unite' => $unite->id, 'seuil' => 5]);
    }
}

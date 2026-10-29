<?php

namespace Tests\Feature\Api\Pharma;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\Pharma\Categorie;
use App\Models\Pharma\Consultation;
use App\Models\Pharma\OrdreSoins;
use App\Models\Pharma\Panier;
use App\Models\Pharma\Service;
use App\Models\Pharma\Vente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrdreSoinsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $dentiste;

    private User $caissier;

    private Consultation $consultation;

    private Service $carie;

    private Service $detartrage;

    protected function setUp(): void
    {
        parent::setUp();

        $employee = Employee::factory()->create(['nom' => 'HERY', 'position' => 'Dentiste']);
        $this->dentiste = User::factory()->create(['employee_id' => $employee->id]);
        $this->dentiste->roles()->sync(Role::whereIn('name', ['personnel', 'dentiste'])->pluck('id'));
        $this->caissier = $this->userWithRole(RoleName::Caissier);

        $categorie = Categorie::create(['nom_cat' => 'Dentisterie']);
        $this->carie = Service::create(['categorie_id' => $categorie->id, 'nom_service' => 'Soin de carie', 'prix_service' => 30000, 'acte_dentaire' => true]);
        $this->detartrage = Service::create(['categorie_id' => $categorie->id, 'nom_service' => 'Détartrage', 'prix_service' => 20000, 'acte_dentaire' => true]);
        Service::create(['categorie_id' => $categorie->id, 'nom_service' => 'Radiographie', 'prix_service' => 40000]);

        $this->consultation = $this->consultation($employee, 'dentiste');
    }

    public function test_the_dentist_picks_treatments_from_the_dental_acts_only(): void
    {
        $this->actingAs($this->dentiste)->getJson('/api/v1/pharma/actes-dentaires')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('data.0.nom', 'Détartrage')->assertJsonPath('data.1.nom', 'Soin de carie');
    }

    public function test_the_dentist_sends_the_patient_to_the_cash_desk_and_the_visit_is_closed(): void
    {
        $this->actingAs($this->dentiste)->postJson("/api/v1/pharma/consultations/{$this->consultation->id}/ordre-soins", [
            'lignes' => [['service_id' => $this->carie->id, 'qte' => 2], ['service_id' => $this->detartrage->id, 'qte' => 1]],
            'note' => 'Dents 36 et 46',
        ])->assertCreated()->assertJsonPath('total', 80000);

        $this->assertSame('vu', $this->consultation->fresh()->statut);
        $this->actingAs($this->dentiste)->getJson('/api/v1/pharma/consultations')->assertOk()
            ->assertJsonPath('data.0.peut_envoyer_en_caisse', true)
            ->assertJsonPath('data.0.ordres.0.statut', 'en_attente')
            ->assertJsonPath('data.0.ordres.0.actes', '2 × Soin de carie, Détartrage');
    }

    public function test_only_the_dentist_of_that_consultation_can_send_soins_and_only_known_acts(): void
    {
        $url = "/api/v1/pharma/consultations/{$this->consultation->id}/ordre-soins";
        $autre = Employee::factory()->create(['position' => 'Dentiste']);
        $compteAutre = User::factory()->create(['employee_id' => $autre->id]);
        $compteAutre->roles()->sync(Role::where('name', 'dentiste')->pluck('id'));

        $this->actingAs($compteAutre)->postJson($url, ['lignes' => [['service_id' => $this->carie->id, 'qte' => 1]]])->assertForbidden();
        $this->actingAs($this->caissier)->postJson($url, ['lignes' => [['service_id' => $this->carie->id, 'qte' => 1]]])->assertForbidden();

        $radio = Service::where('nom_service', 'Radiographie')->firstOrFail();
        $this->actingAs($this->dentiste)->postJson($url, ['lignes' => [['service_id' => $radio->id, 'qte' => 1]]])
            ->assertUnprocessable()->assertJsonPath('message', "Un des actes choisis n'est plus disponible.");
        $this->actingAs($this->dentiste)->postJson($url, ['lignes' => []])->assertUnprocessable()->assertJsonValidationErrors('lignes');

        // Un médecin généraliste n'envoie pas de soins dentaires.
        $docteur = Employee::factory()->create(['position' => 'Docteur']);
        $compteDocteur = User::factory()->create(['employee_id' => $docteur->id]);
        $compteDocteur->roles()->sync(Role::where('name', 'medecin')->pluck('id'));
        $consultationGeneraliste = $this->consultation($docteur, 'generaliste');
        $this->actingAs($compteDocteur)->postJson("/api/v1/pharma/consultations/{$consultationGeneraliste->id}/ordre-soins", ['lignes' => [['service_id' => $this->carie->id, 'qte' => 1]]])
            ->assertForbidden()->assertJsonPath('message', 'Seul le dentiste envoie des soins en caisse.');
    }

    public function test_the_cashier_adds_the_soins_to_the_basket_and_paying_marks_them_as_paid(): void
    {
        $ordre = $this->ordre();
        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ouvrir', ['fond_de_caisse' => 0])->assertCreated();

        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/caisse/ordres-soins')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.patient', 'Rabe')->assertJsonPath('data.0.total', 80000)
            ->assertJsonPath('data.0.dans_mon_panier', false);

        $this->actingAs($this->caissier)->postJson("/api/v1/pharma/caisse/ordres-soins/{$ordre->id}/panier")->assertCreated();
        $this->actingAs($this->caissier)->postJson("/api/v1/pharma/caisse/ordres-soins/{$ordre->id}/panier")
            ->assertUnprocessable()->assertJsonPath('message', 'Ces soins sont déjà dans un panier.');

        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/caisse/panier')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('total', 80000)->assertJsonPath('patient', 'Rabe');
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/caisse/ordres-soins')->assertJsonPath('data.0.dans_mon_panier', true);

        $this->actingAs($this->caissier)->postJson('/api/v1/pharma/caisse/ventes', ['client' => 'Rabe', 'montant_paye' => 80000])->assertCreated();

        $this->assertSame('paye', $ordre->fresh()->statut);
        $this->assertSame(Vente::latest('id')->firstOrFail()->id, $ordre->fresh()->vente_id);
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/caisse/ordres-soins')->assertJsonCount(0, 'data');
        $this->actingAs($this->dentiste)->getJson('/api/v1/pharma/consultations')->assertJsonPath('data.0.ordres.0.statut', 'paye');
    }

    public function test_removing_the_soins_from_the_basket_keeps_them_waiting_for_payment(): void
    {
        $ordre = $this->ordre();
        $this->actingAs($this->caissier)->postJson("/api/v1/pharma/caisse/ordres-soins/{$ordre->id}/panier")->assertCreated();

        $this->actingAs($this->caissier)->deleteJson('/api/v1/pharma/caisse/panier')->assertOk();

        $this->assertSame('en_attente', $ordre->fresh()->statut);
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/caisse/ordres-soins')->assertJsonPath('data.0.dans_mon_panier', false);
    }

    public function test_the_dentist_can_cancel_unpaid_soins_but_not_paid_ones(): void
    {
        $ordre = $this->ordre();
        $this->actingAs($this->caissier)->postJson("/api/v1/pharma/caisse/ordres-soins/{$ordre->id}/panier")->assertCreated();

        $this->actingAs($this->dentiste)->deleteJson("/api/v1/pharma/ordres-soins/{$ordre->id}")->assertOk();
        $this->assertSame('annule', $ordre->fresh()->statut);
        $this->assertSame(0, Panier::count(), 'Les lignes déjà au panier sont retirées.');
        $this->actingAs($this->caissier)->getJson('/api/v1/pharma/caisse/ordres-soins')->assertJsonCount(0, 'data');

        $paye = $this->ordre();
        $paye->update(['statut' => 'paye']);
        $this->actingAs($this->dentiste)->deleteJson("/api/v1/pharma/ordres-soins/{$paye->id}")->assertUnprocessable();
    }

    private function ordre(): OrdreSoins
    {
        $ordre = OrdreSoins::create(['consultation_id' => $this->consultation->id, 'user_id' => $this->dentiste->id, 'statut' => 'en_attente']);
        $ordre->lignes()->create(['service_id' => $this->carie->id, 'qte' => 2]);
        $ordre->lignes()->create(['service_id' => $this->detartrage->id, 'qte' => 1]);

        return $ordre;
    }

    private function consultation(Employee $docteur, string $type): Consultation
    {
        $vente = Vente::create(['reference_vente' => 'V'.fake()->unique()->numerify('##########'), 'user_id' => $this->caissier->id, 'client' => 'Rabe', 'montant_brut' => 10000, 'montant_paye' => 10000, 'monnaie' => 0, 'mode_paiement' => 'cash']);

        return Consultation::create(['vente_id' => $vente->id, 'nom_patient' => 'Rabe', 'sex_patient' => true, 'age_patient' => 30, 'unite_age' => true, 'type_docteur' => $type, 'docteur' => (string) $docteur->id]);
    }
}

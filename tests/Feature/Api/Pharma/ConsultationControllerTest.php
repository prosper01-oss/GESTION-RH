<?php

namespace Tests\Feature\Api\Pharma;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\Pharma\Consultation;
use App\Models\Pharma\Vente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ConsultationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Employee $docteur;

    private Employee $dentiste;

    private User $compteDocteur;

    private User $compteDentiste;

    protected function setUp(): void
    {
        parent::setUp();

        $this->docteur = Employee::factory()->create(['nom' => 'RAKOTO', 'position' => 'Docteur']);
        $this->dentiste = Employee::factory()->create(['nom' => 'HERY', 'position' => 'Dentiste']);
        $this->compteDocteur = $this->compteLie($this->docteur, 'medecin');
        $this->compteDentiste = $this->compteLie($this->dentiste, 'dentiste');
    }

    public function test_each_doctor_only_sees_the_patients_sent_to_them_oldest_first(): void
    {
        $premier = $this->consultation($this->docteur, 'Rabe', now()->subMinutes(30));
        $second = $this->consultation($this->docteur, 'Soa', now()->subMinutes(5));
        $this->consultation($this->dentiste, 'Zo', now());
        $this->consultation($this->docteur, 'Hier', now()->subDay());

        $this->actingAs($this->compteDocteur)->getJson('/api/v1/pharma/consultations')->assertOk()
            ->assertJsonPath('attente', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $premier->id)
            ->assertJsonPath('data.0.patient', 'Rabe')
            ->assertJsonPath('data.0.sexe', 'Homme')
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.1.docteur', 'RAKOTO '.$this->docteur->prenom);

        $this->actingAs($this->compteDentiste)->getJson('/api/v1/pharma/consultations')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.patient', 'Zo');
    }

    public function test_a_patient_is_marked_as_seen_by_their_own_doctor_only(): void
    {
        $consultation = $this->consultation($this->docteur, 'Rabe', now());

        $this->actingAs($this->compteDentiste)->postJson("/api/v1/pharma/consultations/{$consultation->id}/vu")->assertForbidden();
        $this->assertSame('en_attente', $consultation->fresh()->statut);

        $this->actingAs($this->compteDocteur)->postJson("/api/v1/pharma/consultations/{$consultation->id}/vu")->assertOk();
        $this->assertSame('vu', $consultation->fresh()->statut);
        $this->assertNotNull($consultation->fresh()->vu_at);

        $this->actingAs($this->compteDocteur)->postJson("/api/v1/pharma/consultations/{$consultation->id}/vu")->assertUnprocessable();
        $this->actingAs($this->compteDocteur)->getJson('/api/v1/pharma/consultations?statut=en_attente')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('attente', 0);
        $this->actingAs($this->compteDocteur)->getJson('/api/v1/pharma/consultations?statut=vu')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_administrator_sees_every_doctor_and_others_have_no_access(): void
    {
        $this->consultation($this->docteur, 'Rabe', now());
        $this->consultation($this->dentiste, 'Zo', now());

        $this->actingAs($this->userWithRole(RoleName::Administrateur))->getJson('/api/v1/pharma/consultations')->assertOk()->assertJsonCount(2, 'data');
        $this->actingAs($this->userWithRole(RoleName::Caissier))->getJson('/api/v1/pharma/consultations')->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::Personnel))->getJson('/api/v1/pharma/consultations')->assertForbidden();
    }

    public function test_an_account_without_a_staff_record_sees_an_empty_queue(): void
    {
        $this->consultation($this->docteur, 'Rabe', now());
        $sansFiche = User::factory()->create();
        $sansFiche->roles()->sync(Role::where('name', 'medecin')->pluck('id'));

        $this->actingAs($sansFiche)->getJson('/api/v1/pharma/consultations')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('lie', false);
    }

    private function compteLie(Employee $employee, string $role): User
    {
        $user = User::factory()->create(['employee_id' => $employee->id]);
        $user->roles()->sync(Role::whereIn('name', ['personnel', $role])->pluck('id'));

        return $user;
    }

    private function consultation(Employee $docteur, string $patient, $date): Consultation
    {
        $vente = Vente::create(['reference_vente' => 'V'.fake()->unique()->numerify('##########'), 'user_id' => $this->compteDocteur->id, 'client' => $patient, 'montant_brut' => 5000, 'montant_paye' => 5000, 'monnaie' => 0, 'mode_paiement' => 'cash']);

        $consultation = Consultation::create(['vente_id' => $vente->id, 'nom_patient' => $patient, 'sex_patient' => true, 'age_patient' => 30, 'unite_age' => true, 'type_docteur' => 'generaliste', 'docteur' => (string) $docteur->id]);
        $consultation->forceFill(['created_at' => $date])->save();

        return $consultation;
    }
}

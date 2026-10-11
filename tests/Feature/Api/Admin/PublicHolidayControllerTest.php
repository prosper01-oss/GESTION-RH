<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleName;
use App\Models\PublicHoliday;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicHolidayControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_recurring_holidays_and_those_of_the_requested_year(): void
    {
        PublicHoliday::factory()->recurring()->create(['date' => '2000-06-26', 'label' => 'Indépendance']);
        PublicHoliday::factory()->create(['date' => '2026-04-06', 'label' => 'Lundi de Pâques 2026']);
        PublicHoliday::factory()->create(['date' => '2025-04-21', 'label' => 'Lundi de Pâques 2025']);

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->getJson('/api/v1/admin/public-holidays?year=2026')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', 'Lundi de Pâques 2026')
            ->assertJsonPath('data.1.label', 'Indépendance');
    }

    public function test_rh_adds_a_holiday_and_a_duplicate_date_returns_422(): void
    {
        $rh = $this->userWithRole(RoleName::RessourcesHumaines);

        $this->actingAs($rh)->postJson('/api/v1/admin/public-holidays', ['date' => '2026-05-14', 'label' => 'Ascension', 'is_recurring' => false])
            ->assertCreated();

        $this->actingAs($rh)->postJson('/api/v1/admin/public-holidays', ['date' => '2026-05-14', 'label' => 'Doublon', 'is_recurring' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date' => 'Un jour férié existe déjà à cette date.']);
    }

    public function test_rh_deletes_a_holiday(): void
    {
        $holiday = PublicHoliday::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->deleteJson("/api/v1/admin/public-holidays/{$holiday->id}")
            ->assertOk();

        $this->assertModelMissing($holiday);
        $this->assertDatabaseHas('audit_logs', ['action' => 'public_holiday.deleted', 'entity_id' => $holiday->id]);
    }

    public function test_medecin_chef_gets_403(): void
    {
        $this->actingAs($this->userWithRole(RoleName::MedecinChef))
            ->postJson('/api/v1/admin/public-holidays', ['date' => '2026-05-14', 'label' => 'Ascension', 'is_recurring' => false])
            ->assertForbidden();
    }
}

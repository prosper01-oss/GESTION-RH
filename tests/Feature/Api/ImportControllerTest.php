<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dry_run_validates_without_saving_then_import_creates_employees_and_services(): void
    {
        Department::factory()->create(['name' => 'Médecine du travail']);
        $rh = $this->userWithRole(RoleName::RessourcesHumaines);
        $csv = "matricule;nom;prénom;service;fonction;date_embauche;type_contrat;telephone;email\n"
            ."OMIT-001;Rasoanaivo;Hery;Médecine du travail;Infirmier;01/03/2020;CDI;;\n"
            ."OMIT-002;Rabe;Fanja;Laboratoire;Laborantine;2019-09-15;cdd;+261 34 00 000 00;fanja@omit.mg\n";

        $this->actingAs($rh)->post('/api/v1/employees/import', ['file' => $this->csv($csv), 'dry_run' => 1])
            ->assertOk()
            ->assertJsonPath('data.rows', 2)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.new_departments', ['Laboratoire']);
        $this->assertDatabaseCount('employees', 0);

        $this->actingAs($rh)->post('/api/v1/employees/import', ['file' => $this->csv($csv)])->assertOk()->assertJsonPath('data.created', 2);

        $this->assertSame('2020-03-01', Employee::where('matricule', 'OMIT-001')->sole()->hire_date->toDateString());
        $this->assertDatabaseHas('departments', ['name' => 'Laboratoire']);
    }

    public function test_one_invalid_line_blocks_the_whole_import_and_is_reported(): void
    {
        Employee::factory()->create(['matricule' => 'OMIT-009']);
        $csv = "matricule;nom;prenom;service;fonction;date_embauche;type_contrat\n"
            ."OMIT-001;Rasoanaivo;Hery;Laboratoire;Infirmier;01/03/2020;cdi\n"
            ."OMIT-009;Rabe;Fanja;Laboratoire;Laborantine;31/02/2019;interim\n";

        $response = $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))->post('/api/v1/employees/import', ['file' => $this->csv($csv)]);

        $response->assertUnprocessable()->assertJsonPath('data.errors.0.line', 3);
        $this->assertCount(3, $response->json('data.errors.0.messages'));
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_missing_columns_return_422(): void
    {
        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->post('/api/v1/employees/import', ['file' => $this->csv("matricule;nom\nA;B\n")], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_balances_import_records_justified_corrections(): void
    {
        $employee = Employee::factory()->create(['matricule' => 'OMIT-001']);
        LeaveType::factory()->withPolicy()->create(['code' => 'CA']);
        $csv = "matricule,type_conge,annee,jours,motif\nOMIT-001,ca,2026,\"12,5\",\nOMIT-404,CA,2026,3,\n";
        $rh = $this->userWithRole(RoleName::RessourcesHumaines);

        $this->actingAs($rh)->post('/api/v1/leave-balances/import', ['file' => $this->csv($csv)])
            ->assertUnprocessable()
            ->assertJsonPath('data.errors.0.messages.0', 'Matricule inconnu : OMIT-404.');

        $this->actingAs($rh)->post('/api/v1/leave-balances/import', ['file' => $this->csv("matricule,type_conge,annee,jours,motif\nOMIT-001,ca,2026,\"12,5\",\n")])
            ->assertOk()->assertJsonPath('data.created', 1);
        $this->assertDatabaseHas('leave_balance_entries', ['entry_type' => 'correction', 'days' => 12.5, 'reason' => 'Reprise des soldes existants']);
        $this->assertSame(1, $employee->leaveBalances()->count());
    }

    public function test_windows_1252_file_is_read_correctly(): void
    {
        $csv = mb_convert_encoding("matricule;nom;prenom;service;fonction;date_embauche;type_contrat\nOMIT-001;Rasoanaivo;Héry;Médecine;Infirmière;01/03/2020;cdi\n", 'Windows-1252', 'UTF-8');

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))->post('/api/v1/employees/import', ['file' => $this->csv($csv)])->assertOk();

        $this->assertDatabaseHas('employees', ['prenom' => 'Héry', 'position' => 'Infirmière']);
    }

    public function test_medecin_chef_gets_403_and_template_downloads(): void
    {
        $this->actingAs($this->userWithRole(RoleName::MedecinChef))
            ->post('/api/v1/employees/import', ['file' => $this->csv("a\nb\n")], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))->get('/api/v1/imports/templates/personnel')
            ->assertOk()
            ->assertDownload('modele-import-personnel.csv');
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }
}

<?php

namespace Tests\Feature\Console;

use App\Enums\EmployeeStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Import\RegistreImporter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportRegistrePersonnelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $fichier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fichier = tempnam(sys_get_temp_dir(), 'registre').'.xlsx';
    }

    protected function tearDown(): void
    {
        @unlink($this->fichier);

        parent::tearDown();
    }

    public function test_agents_missing_from_the_staff_list_are_added_from_the_register(): void
    {
        Department::factory()->create(['name' => 'MEDECIN', 'code' => 'SRV-01']);
        $deja = Employee::factory()->create(['matricule' => '70', 'nom' => 'RANDRIANANDRASANA', 'position' => 'Servant']);
        $this->registre([
            ['70', 'RANDRIANANDRASANA Félix', 'M', 'Vers 1977 à Ambolonaondry', '212 091 000 391 du 24/02/95 à Vohipeno', '770101029367', 'Marié', 'Anketa', 'Ecrit', '13/07/1998', '', 'Servant'],
            ['71', 'RAKOTO Jean', 'M', 'Vers 1977 à Ambolonaondry', '', '', '', '', 'Ecrit', '13/07/1998', '', 'Gardien'],
            ['99', 'MAZOTOARISOA Andréa', 'F', '08/01/90 à Bezaha', '501 092 012 406 du 02/11/09 à Toliara I', '902108004419', 'Cel', 'Anketa', 'Ecrit', '19/08/2019', '', 'Infirmière'],
            ['115', 'RAZANAMIARANA Alaine Florence', 'F', '24/02/95 à Ankatsaka', '501 012 010 441 du 11/03/13', '952224002187', '', 'Ambohitsabo', 'Ecrit', '01/03/2021', '31/07/2026', 'Secretaire à la pharmacie'],
            ['153', 'VOARA NOMENJANAHARY Iarimenana Ariella', 'F', '04/05/1990', '501 112 017 012 du 12/03/09', '902504006111', 'Cel', 'Tsianaloka', 'Ecrit', '01/10/2025', '', 'Sage-femme'],
            ['162', 'RAJAOMANAMBINA Tahintsoa Patrick', 'M', '13/07/99 à Ihosy', '216 011 025 205', '990713005336', 'Cel', 'Andaboly', 'Ecrit', '09/02/2026', '', 'Médecin Stomato'],
            ['136', 'AMBININTSOA Innocent', 'M', '24/04/05 à Toliara', '520 031 011 144', '50424000252', 'Cel', 'Belalanda', 'Ecrit', '01/06/2024', '', 'Jardinnier'],
        ]);

        $this->artisan('omit:import-registre', ['fichier' => $this->fichier, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(1, Employee::count(), 'La simulation n\'enregistre rien.');

        $this->artisan('omit:import-registre', ['fichier' => $this->fichier])->expectsOutputToContain('6 fiche(s) créée(s)')->assertSuccessful();

        $this->assertSame(7, Employee::count());
        $this->assertSame('Servant', $deja->fresh()->position, 'Une fiche existante n\'est jamais modifiée.');

        $mazotoarisoa = Employee::where('matricule', '99')->firstOrFail();
        $this->assertSame(['MAZOTOARISOA', 'Andréa'], [$mazotoarisoa->nom, $mazotoarisoa->prenom]);
        $this->assertSame('VISITE et URGENCE', $mazotoarisoa->department->name);
        $this->assertSame('1990-01-08', $mazotoarisoa->date_naissance->toDateString());
        $this->assertSame('501 092 012 406', $mazotoarisoa->cin);
        $this->assertSame('902108004419', $mazotoarisoa->numero_cnasp);
        $this->assertSame('feminin', $mazotoarisoa->sexe->value);
        $this->assertSame('celibataire', $mazotoarisoa->situation_familiale->value);
        $this->assertSame('2019-08-19', $mazotoarisoa->hire_date->toDateString());
        $this->assertSame(EmployeeStatus::Actif, $mazotoarisoa->status);

        $this->assertSame(['VOARA NOMENJANAHARY', 'Iarimenana Ariella'], array_values(Employee::where('matricule', '153')->firstOrFail()->only(['nom', 'prenom'])));
        $this->assertSame('2005-04-24', Employee::where('matricule', '136')->firstOrFail()->date_naissance->toDateString());
        $this->assertNull(Employee::where('matricule', '71')->firstOrFail()->date_naissance, 'Une naissance « Vers 1977 » n\'a pas de date.');

        $this->assertSame(EmployeeStatus::Inactif, Employee::where('matricule', '115')->firstOrFail()->status);
        $this->assertTrue(Department::where('name', 'JARDINAGE')->exists(), 'Un service manquant est créé.');
        $this->assertSame('DENTISTE', Employee::where('matricule', '162')->firstOrFail()->department->name);

        // Relancer l'import ne crée aucun doublon.
        $this->artisan('omit:import-registre', ['fichier' => $this->fichier])->expectsOutputToContain('0 fiche(s) créée(s)')->assertSuccessful();
        $this->assertSame(7, Employee::count());
    }

    public function test_the_whole_import_is_refused_when_a_function_has_no_service(): void
    {
        $this->registre([
            ['99', 'MAZOTOARISOA Andréa', 'F', '', '', '', '', '', 'Ecrit', '19/08/2019', '', 'Infirmière'],
            ['98', 'DUPONT Jean', 'M', '', '', '', '', '', 'Ecrit', '19/08/2019', '', 'Astronaute'],
        ]);

        $this->artisan('omit:import-registre', ['fichier' => $this->fichier])
            ->expectsOutputToContain('aucun service pour la fonction « Astronaute »')
            ->assertFailed();
        $this->assertSame(0, Employee::count());
    }

    public function test_names_are_split_between_surname_and_given_names(): void
    {
        $importer = app(RegistreImporter::class);

        $this->assertSame(['RANDRIANANDRASANA', 'Félix'], $importer->splitName('RANDRIANANDRASANA Félix'));
        $this->assertSame(['MAKA MAMPANITRA', 'Georgia Ferlandine'], $importer->splitName(' MAKA MAMPANITRA Georgia Ferlandine'));
        $this->assertSame(['SOKOROKANA', 'R. Corinne Lucienne'], $importer->splitName('SOKOROKANA R. Corinne Lucienne'));
        $this->assertSame(['ASMINA', 'ASMINA'], $importer->splitName('ASMINA'));
        $this->assertSame(['RAFARAMBOAVONJY', 'Anastasie Elisa Barson'], $importer->splitName('RAFARAMBOAVONJY Anastasie Elisa Barson   '));
    }

    /**
     * @param  list<list<string>>  $lignes
     */
    private function registre(array $lignes): void
    {
        $classeur = new Spreadsheet;
        $feuille = $classeur->getActiveSheet()->setTitle('REGISTRE');
        $feuille->fromArray([['OMIT - TOLIARA'], [], [], ['N° IM', 'NOM & PRENOMS', 'SEXE', 'DATE ET LIEU DE NAISSANCE', 'CIN', 'CNAPS', 'SITUATION FAMILIALE', 'RESIDENCE', "NATURE DE L'ENGAGEMENT", "DATE DE L'ENGAGEMENT", 'DATE DU DEPART', 'OBSERVATIONS']], null, 'A1', true);
        $feuille->fromArray($lignes, null, 'A5', true);
        (new Xlsx($classeur))->save($this->fichier);
    }
}

<?php

namespace Tests\Feature\Console;

use App\Models\Pharma\Analyse;
use App\Models\Pharma\Article;
use App\Models\Pharma\Categorie;
use App\Models\Pharma\Kit;
use App\Models\Pharma\Unite;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ImportCataloguePharmaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_catalogue_is_imported_from_a_legacy_dump_keeping_ids_and_cleaning_names(): void
    {
        $fichier = tempnam(sys_get_temp_dir(), 'omitpharma').'.sql';
        file_put_contents($fichier, <<<'SQL'
        CREATE TABLE `analyses` (`id` bigint(20) UNSIGNED NOT NULL);
        INSERT INTO `unites` (`id`, `nomUnite`, `nomComplet`, `supun`, `created_at`, `updated_at`) VALUES
        (1, 'Ut', 'Ut', 0, NULL, NULL),
        (2, 'BT', 'Boîte', 0, NULL, NULL);
        INSERT INTO `categories` (`id`, `nom_cat`, `etat`, `created_at`, `updated_at`) VALUES
        (1, 'consultation', 1, '2026-03-19 06:15:30', '2026-03-19 06:15:30'),
        (3, 'Hospitalisation', 0, '2026-03-19 06:16:45', '2026-04-10 03:43:42');
        INSERT INTO `analyses` (`id`, `nom`, `prix`, `etat`, `created_at`, `updated_at`) VALUES
        (1, 'NFS', 16200.00, 1, '2026-03-19 04:51:52', '2026-03-19 04:51:52'),
        (19, 'Examen de selle', 7200.00, 1, NULL, NULL);
        INSERT INTO `kits` (`id`, `nom_kit`, `prix_kit`, `etat`, `created_at`, `updated_at`) VALUES
        (5, 'CHAMBRE VIP', 30000.00, 1, NULL, NULL);
        INSERT INTO `articles` (`id`, `designation`, `presentation`, `unite`, `stock`, `seuil`, `qte_max`, `statut`, `etat`, `created_at`, `updated_at`) VALUES
        (3, 'ABS Pommade  tube', 1, 2, 0, 10, NULL, 1, 1, '2024-12-09 23:29:41', '2026-01-21 04:36:56'),
        (22, ' AMLOPRESS -10 B/100', 100, 1, 0, 10, NULL, 1, 1, '2024-12-09 23:34:45', '2025-05-27 03:25:41'),
        (275, 'Hydroxyde d\'alluminium 500MG CPR B/100, (virgule) et ''doubles''', 100, 1, 0, 10, 60, 1, 0, NULL, NULL);
        INSERT INTO `echographies` (`id`, `nom`, `prix`, `etat`, `created_at`, `updated_at`) VALUES
        (1, 'hebdo', 25000.00, 1, NULL, NULL);
        SQL);

        $this->artisan('omit:pharma-import-catalogue', ['fichier' => $fichier])
            ->expectsOutputToContain('pharma_articles')
            ->assertSuccessful();

        $this->assertSame(2, Unite::count());
        $this->assertSame(['consultation', 'Hospitalisation'], Categorie::orderBy('id')->pluck('nom_cat')->all());
        $this->assertFalse(Categorie::findOrFail(3)->etat);
        $this->assertSame(7200.0, Analyse::findOrFail(19)->prix);
        $this->assertSame(30000.0, Kit::findOrFail(5)->prix_kit);

        $this->assertSame([3, 22, 275], Article::orderBy('id')->pluck('id')->all());
        $this->assertSame('AMLOPRESS -10 B/100', Article::findOrFail(22)->designation);
        $this->assertSame("Hydroxyde d'alluminium 500MG CPR B/100, (virgule) et 'doubles'", Article::findOrFail(275)->designation);
        $this->assertSame(60, Article::findOrFail(275)->qte_max);
        $this->assertSame(0, Article::findOrFail(275)->etat);
        $this->assertSame('2024-12-09 23:29:41', Article::findOrFail(3)->created_at->format('Y-m-d H:i:s'));

        // Les autres tables de l'ancienne base ne sont pas reprises, et relancer l'import ne crée pas de doublons.
        $this->assertDatabaseCount('pharma_echographies', 0);
        $this->artisan('omit:pharma-import-catalogue', ['fichier' => $fichier])->assertSuccessful();
        $this->assertSame(3, Article::count());

        unlink($fichier);
    }

    public function test_a_missing_file_is_reported(): void
    {
        $this->artisan('omit:pharma-import-catalogue', ['fichier' => '/tmp/inexistant.sql'])
            ->expectsOutputToContain('Fichier introuvable')
            ->assertFailed();
    }
}

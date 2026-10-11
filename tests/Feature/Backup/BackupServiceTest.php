<?php

namespace Tests\Feature\Backup;

use App\Models\Employee;
use App\Services\Backup\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * REC-13 : une sauvegarde est restaurée avec succès, sur une base et un stockage isolés.
 */
class BackupServiceTest extends TestCase
{
    protected bool $seed = false;

    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir().'/omit-backup-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists("{$this->sandbox}/storage/app/private");
        touch("{$this->sandbox}/database.sqlite");

        $this->app->useStoragePath("{$this->sandbox}/storage");
        config([
            'database.connections.backup_sandbox' => ['driver' => 'sqlite', 'database' => "{$this->sandbox}/database.sqlite", 'prefix' => '', 'foreign_key_constraints' => true],
            'database.default' => 'backup_sandbox',
            'omit.backup.path' => "{$this->sandbox}/backups",
            'omit.backup.copy_path' => "{$this->sandbox}/external",
        ]);
        Artisan::call('migrate', ['--database' => 'backup_sandbox', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('backup_sandbox');
        File::deleteDirectory($this->sandbox);

        parent::tearDown();
    }

    public function test_backup_is_copied_to_the_second_medium_and_restores_database_and_files(): void
    {
        $employee = Employee::factory()->create(['nom' => 'Rasoanaivo']);
        File::put(storage_path('app/private/justificatif.pdf'), '%PDF-1.4 contenu');
        $backups = app(BackupService::class);

        $result = $backups->create();

        $this->assertFileExists($result['path']);
        $this->assertFileExists($result['copied_to']);
        $this->assertSame(substr(sprintf('%o', fileperms($result['path'])), -4), '0600');

        $employee->delete();
        File::delete(storage_path('app/private/justificatif.pdf'));
        File::put(storage_path('app/private/apres.txt'), 'créé après la sauvegarde');

        $backups->restore($result['path']);
        DB::reconnect('backup_sandbox');

        $this->assertSame('Rasoanaivo', Employee::find($employee->id)?->nom);
        $this->assertSame('%PDF-1.4 contenu', File::get(storage_path('app/private/justificatif.pdf')));
        $this->assertFileDoesNotExist(storage_path('app/private/apres.txt'));
    }

    public function test_archives_older_than_the_retention_are_pruned(): void
    {
        File::ensureDirectoryExists("{$this->sandbox}/backups");
        $old = "{$this->sandbox}/backups/omit-backup-20200101-000000.tar.gz";
        File::put($old, 'ancienne');
        touch($old, now()->subDays(31)->getTimestamp());

        $result = app(BackupService::class)->create();

        $this->assertSame(1, $result['pruned']);
        $this->assertFileDoesNotExist($old);
        $this->assertCount(1, app(BackupService::class)->list());
    }
}

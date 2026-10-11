<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\Backup\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('omit:backup-restore {archive : Chemin de l\'archive .tar.gz} {--force : Ne pas demander de confirmation}')]
#[Description('Restaure une sauvegarde : REMPLACE la base et les fichiers privés actuels')]
class RestoreBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BackupService $backups): int
    {
        $archive = $this->argument('archive');

        $this->warn('La restauration remplace TOUTES les données actuelles (base, pièces jointes, photos).');
        if (! $this->option('force') && $this->ask('Tapez RESTAURER pour confirmer') !== 'RESTAURER') {
            $this->info('Restauration abandonnée.');

            return self::FAILURE;
        }

        try {
            $manifest = $backups->restore($archive);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Restauration échouée : '.$exception->getMessage());

            return self::FAILURE;
        }

        AuditLog::record('backup.restored', newValues: ['file' => basename($archive), 'backup_date' => $manifest['created_at'] ?? null]);
        $this->info('Restauration terminée (sauvegarde du '.($manifest['created_at'] ?? '?').').');

        return self::SUCCESS;
    }
}

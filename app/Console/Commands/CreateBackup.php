<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\Backup\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Number;
use Throwable;

#[Signature('omit:backup')]
#[Description('Sauvegarde la base et les fichiers privés, copie l\'archive sur le second support et supprime les archives périmées')]
class CreateBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->create();
        } catch (Throwable $exception) {
            report($exception);
            AuditLog::record('backup.failed', newValues: ['error' => $exception->getMessage()]);
            $this->error('Sauvegarde échouée : '.$exception->getMessage());

            return self::FAILURE;
        }

        AuditLog::record('backup.created', newValues: ['file' => basename($result['path']), 'size' => $result['size'], 'copied' => $result['copied_to'] !== null]);

        $this->info('Sauvegarde créée : '.$result['path'].' ('.Number::fileSize($result['size']).')');
        $this->line($result['copied_to'] ? "Copie : {$result['copied_to']}" : 'Aucune copie : OMIT_BACKUP_COPY_PATH n\'est pas renseigné.');
        $this->line("{$result['pruned']} archive(s) périmée(s) supprimée(s).");

        return self::SUCCESS;
    }
}

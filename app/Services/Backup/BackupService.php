<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Sauvegarde et restauration de la base et des fichiers privés (pièces jointes, photos).
 * Une archive contient : database.sql (MySQL) ou database.sqlite, le dossier files/ et manifest.json.
 */
class BackupService
{
    private const PREFIX = 'omit-backup-';

    /**
     * Crée une archive, la copie sur le second support et supprime les archives trop anciennes.
     *
     * @return array{path: string, size: int, copied_to: ?string, pruned: int}
     */
    public function create(): array
    {
        $directory = $this->ensureDirectory(config('omit.backup.path'));
        $work = $this->temporaryDirectory();

        try {
            $connection = config('database.default');
            $this->dumpDatabase($connection, $work);
            File::copyDirectory(storage_path('app/private'), "{$work}/files");
            File::put("{$work}/manifest.json", json_encode([
                'created_at' => now()->toIso8601String(),
                'app' => config('app.name'),
                'database_driver' => config("database.connections.{$connection}.driver"),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $archive = $directory.'/'.self::PREFIX.now()->format('Ymd-His').'.tar.gz';
            $this->run(['tar', '-czf', $archive, '-C', $work, '.'], 'Création de l\'archive');
            chmod($archive, 0600);
        } finally {
            File::deleteDirectory($work);
        }

        $copiedTo = null;
        if ($copyPath = config('omit.backup.copy_path')) {
            $copiedTo = $this->ensureDirectory($copyPath).'/'.basename($archive);
            File::copy($archive, $copiedTo);
        }

        return [
            'path' => $archive,
            'size' => File::size($archive),
            'copied_to' => $copiedTo,
            'pruned' => $this->prune(),
        ];
    }

    /**
     * Archives présentes sur le serveur, les plus récentes d'abord.
     *
     * @return list<array{name: string, size: int, created_at: string, copied: bool}>
     */
    public function list(): array
    {
        $directory = config('omit.backup.path');
        $copyPath = config('omit.backup.copy_path');

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::glob($directory.'/'.self::PREFIX.'*.tar.gz'))
            ->sortDesc()
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'size' => File::size($path),
                'created_at' => date(DATE_ATOM, File::lastModified($path)),
                'copied' => $copyPath !== null && File::exists($copyPath.'/'.basename($path)),
            ])
            ->values()
            ->all();
    }

    /**
     * Restaure une archive : la base est remplacée, les fichiers privés aussi.
     *
     * @return array<string, mixed> Le manifeste de l'archive restaurée.
     */
    public function restore(string $archive): array
    {
        if (! File::exists($archive)) {
            throw new RuntimeException("Archive introuvable : {$archive}");
        }

        $work = $this->temporaryDirectory();

        try {
            $this->run(['tar', '-xzf', $archive, '-C', $work], 'Extraction de l\'archive');
            $manifest = json_decode((string) File::get("{$work}/manifest.json"), true) ?? throw new RuntimeException('Manifeste absent ou illisible.');

            $connection = config('database.default');
            $this->restoreDatabase($connection, $work);

            File::deleteDirectory(storage_path('app/private'), preserve: true);
            File::copyDirectory("{$work}/files", storage_path('app/private'));

            return $manifest;
        } finally {
            File::deleteDirectory($work);
        }
    }

    private function dumpDatabase(string $connection, string $work): void
    {
        $config = config("database.connections.{$connection}");

        match ($config['driver']) {
            'sqlite' => DB::connection($connection)->statement('VACUUM INTO ?', ["{$work}/database.sqlite"]),
            'mysql', 'mariadb' => $this->run([
                'mysqldump', '--single-transaction', '--routines', '--no-tablespaces', '--default-character-set=utf8mb4',
                '-h', $config['host'], '-P', (string) $config['port'], '-u', $config['username'],
                "--result-file={$work}/database.sql", $config['database'],
            ], 'Export de la base MySQL', ['MYSQL_PWD' => (string) $config['password']]),
            default => throw new RuntimeException("Pilote de base non pris en charge : {$config['driver']}"),
        };
    }

    private function restoreDatabase(string $connection, string $work): void
    {
        $config = config("database.connections.{$connection}");

        if ($config['driver'] === 'sqlite') {
            if (! File::exists("{$work}/database.sqlite") || $config['database'] === ':memory:') {
                throw new RuntimeException('Cette archive ou cette base ne permet pas une restauration SQLite.');
            }
            DB::disconnect($connection);
            File::copy("{$work}/database.sqlite", $config['database']);

            return;
        }

        if (! File::exists("{$work}/database.sql")) {
            throw new RuntimeException("L'archive ne contient pas d'export MySQL.");
        }

        $result = Process::env(['MYSQL_PWD' => (string) $config['password']])
            ->input(File::get("{$work}/database.sql"))
            ->timeout(3600)
            ->run(['mysql', '-h', $config['host'], '-P', (string) $config['port'], '-u', $config['username'], $config['database']]);

        if ($result->failed()) {
            throw new RuntimeException('Restauration MySQL échouée : '.$result->errorOutput());
        }
    }

    private function prune(): int
    {
        $limit = now()->subDays(config('omit.backup.retention_days'))->getTimestamp();
        $pruned = 0;

        foreach (array_filter([config('omit.backup.path'), config('omit.backup.copy_path')]) as $directory) {
            foreach (File::glob($directory.'/'.self::PREFIX.'*.tar.gz') as $path) {
                if (File::lastModified($path) < $limit) {
                    File::delete($path);
                    $pruned++;
                }
            }
        }

        return $pruned;
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    private function run(array $command, string $step, array $env = []): void
    {
        $result = Process::env($env)->timeout(3600)->run($command);

        if ($result->failed()) {
            throw new RuntimeException("{$step} échouée : ".trim($result->errorOutput()));
        }
    }

    private function ensureDirectory(string $path): string
    {
        File::ensureDirectoryExists($path, 0700);

        return rtrim($path, '/');
    }

    private function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir().'/omit-backup-work-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($path, 0700);

        return $path;
    }
}

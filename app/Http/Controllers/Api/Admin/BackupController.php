<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Backup\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

/**
 * État des sauvegardes (section 10.1). Les archives ne sont pas téléchargeables par le web :
 * elles contiennent des données RH et médicales.
 */
class BackupController extends Controller
{
    public function index(BackupService $backups): JsonResponse
    {
        Gate::authorize(PermissionName::ManageBackups->value);

        $lastFailure = AuditLog::query()->where('action', 'backup.failed')->latest('id')->first();
        $lastSuccess = AuditLog::query()->where('action', 'backup.created')->latest('id')->first();

        return response()->json([
            'data' => $backups->list(),
            'copy_configured' => config('omit.backup.copy_path') !== null,
            'retention_days' => config('omit.backup.retention_days'),
            'last_success_at' => $lastSuccess?->created_at,
            'last_failure' => $lastFailure && (! $lastSuccess || $lastFailure->id > $lastSuccess->id)
                ? ['at' => $lastFailure->created_at, 'error' => $lastFailure->new_values['error'] ?? null]
                : null,
        ]);
    }

    public function store(): JsonResponse
    {
        Gate::authorize(PermissionName::ManageBackups->value);

        $exitCode = Artisan::call('omit:backup');

        return $exitCode === 0
            ? response()->json(['message' => 'Sauvegarde effectuée.'], 201)
            : response()->json(['message' => trim(Artisan::output())], 500);
    }
}

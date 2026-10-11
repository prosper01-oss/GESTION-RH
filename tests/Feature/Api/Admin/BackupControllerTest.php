<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\RoleName;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_sees_the_last_failure_when_it_follows_the_last_success(): void
    {
        config(['omit.backup.path' => sys_get_temp_dir().'/omit-backup-empty-'.bin2hex(random_bytes(4))]);
        AuditLog::record('backup.created', newValues: ['file' => 'a.tar.gz']);
        AuditLog::record('backup.failed', newValues: ['error' => 'Disque plein']);

        $this->actingAs($this->userWithRole(RoleName::Administrateur))->getJson('/api/v1/admin/backups')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('retention_days', 30)
            ->assertJsonPath('last_failure.error', 'Disque plein');
    }

    public function test_admin_launches_a_backup(): void
    {
        Artisan::shouldReceive('call')->once()->with('omit:backup')->andReturn(0);

        $this->actingAs($this->userWithRole(RoleName::Administrateur))->postJson('/api/v1/admin/backups')->assertCreated();
    }

    public function test_rh_gets_403(): void
    {
        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))->getJson('/api/v1/admin/backups')->assertForbidden();
    }
}

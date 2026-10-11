<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeePhotoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rh_uploads_a_photo_to_private_storage_and_replaces_the_old_one(): void
    {
        Storage::fake();
        $employee = Employee::factory()->create(['photo_path' => null]);
        $employee->forceFill(['photo_path' => UploadedFile::fake()->image('ancienne.jpg')->store('employee-photos')])->save();
        $oldPath = $employee->photo_path;

        $response = $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->postJson("/api/v1/employees/{$employee->id}/photo", ['photo' => UploadedFile::fake()->image('photo.jpg', 400, 400)]);

        $response->assertOk();
        $newPath = $employee->fresh()->photo_path;
        Storage::assertExists($newPath);
        Storage::assertMissing($oldPath);
        $this->assertStringStartsWith('employee-photos/', $newPath);
    }

    public function test_non_image_upload_returns_422(): void
    {
        Storage::fake();
        $employee = Employee::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->postJson("/api/v1/employees/{$employee->id}/photo", ['photo' => UploadedFile::fake()->create('photo.pdf', 100, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_agent_can_download_own_photo(): void
    {
        Storage::fake();
        $employee = Employee::factory()->create();
        $employee->forceFill(['photo_path' => UploadedFile::fake()->image('photo.jpg')->store('employee-photos')])->save();
        $agent = User::factory()->forEmployee($employee)->create();

        $this->actingAs($agent)->get("/api/v1/employees/{$employee->id}/photo")->assertOk();
    }

    public function test_agent_gets_403_on_another_employee_photo(): void
    {
        Storage::fake();
        $employee = Employee::factory()->create();
        $employee->forceFill(['photo_path' => UploadedFile::fake()->image('photo.jpg')->store('employee-photos')])->save();

        $this->actingAs(User::factory()->forEmployee()->create())
            ->getJson("/api/v1/employees/{$employee->id}/photo")
            ->assertForbidden();
    }

    public function test_missing_photo_returns_404(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->getJson("/api/v1/employees/{$employee->id}/photo")
            ->assertNotFound();
    }
}

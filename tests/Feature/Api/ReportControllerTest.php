<?php

namespace Tests\Feature\Api;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\LeaveType;
use App\Models\PermissionType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_counts_staff_pending_queues_and_absences_today(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14));
        $department = Department::factory()->create(['name' => 'Médecine du travail']);
        Employee::factory()->count(2)->for($department)->create();
        Employee::factory()->inactive()->for($department)->create();
        $leaveType = LeaveType::factory()->withPolicy()->create();
        EmployeeRequest::factory()->leave($leaveType, ['start_date' => '2026-10-12', 'end_date' => '2026-10-16'])->status(RequestStatus::Finalisee)->create();
        EmployeeRequest::factory()->leave($leaveType)->status(RequestStatus::Soumise)->create();
        EmployeeRequest::factory()->leave($leaveType)->status(RequestStatus::TransmiseMedecin)->create();

        $response = $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))->getJson('/api/v1/reports/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.active_employees', 5)
            ->assertJsonPath('data.pending.control', 1)
            ->assertJsonPath('data.pending.decide', 1)
            ->assertJsonPath('data.absent_today', 1)
            ->assertJsonPath('data.leaves_granted_this_month', 1)
            ->assertJsonFragment(['name' => 'Médecine du travail', 'count' => 2]);
    }

    public function test_absences_report_keeps_granted_requests_overlapping_the_period_with_totals(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create(['name' => 'Congé annuel']);
        $inPeriod = EmployeeRequest::factory()->leave($leaveType, ['start_date' => '2026-09-28', 'end_date' => '2026-10-03', 'requested_days' => 6])->status(RequestStatus::Terminee)->create();
        EmployeeRequest::factory()->leave($leaveType, ['start_date' => '2026-10-05', 'end_date' => '2026-10-06', 'requested_days' => 2])->status(RequestStatus::Soumise)->create();
        EmployeeRequest::factory()->leave($leaveType, ['start_date' => '2026-11-02', 'end_date' => '2026-11-03', 'requested_days' => 2])->status(RequestStatus::Finalisee)->create();
        $permission = EmployeeRequest::factory()->status(RequestStatus::Finalisee)->create(['request_type' => RequestType::Permission]);
        $permission->permissionDetail()->create(['permission_type_id' => PermissionType::factory()->create()->id, 'start_at' => '2026-10-20 08:00', 'end_at' => '2026-10-20 11:30', 'duration_hours' => 3.5]);

        $response = $this->actingAs($this->userWithRole(RoleName::MedecinChef))->getJson('/api/v1/reports/absences?from=2026-10-01&to=2026-10-31');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.reference', $inPeriod->reference)
            ->assertJsonPath('data.0.days', 6)
            ->assertJsonPath('data.0.nature', 'Congé annuel')
            ->assertJsonPath('totals.conge.days', 6)
            ->assertJsonPath('totals.permission.hours', 3.5);
    }

    public function test_absences_report_filters_by_department_and_type(): void
    {
        $leaveType = LeaveType::factory()->withPolicy()->create();
        $department = Department::factory()->create();
        EmployeeRequest::factory()->for(Employee::factory()->for($department))->leave($leaveType, ['start_date' => '2026-10-05', 'end_date' => '2026-10-06'])->status(RequestStatus::Finalisee)->create();
        EmployeeRequest::factory()->leave($leaveType, ['start_date' => '2026-10-05', 'end_date' => '2026-10-06'])->status(RequestStatus::Finalisee)->create();

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->getJson("/api/v1/reports/absences?from=2026-10-01&to=2026-10-31&department_id={$department->id}&type=conge")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function formats(): array
    {
        return [
            'pdf' => ['pdf', 'application/pdf'],
            'excel' => ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];
    }

    #[DataProvider('formats')]
    public function test_absences_export_downloads_the_requested_format(string $format, string $contentType): void
    {
        EmployeeRequest::factory()->leave(LeaveType::factory()->withPolicy()->create(), ['start_date' => '2026-10-05', 'end_date' => '2026-10-06'])->status(RequestStatus::Finalisee)->create();

        $response = $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->get("/api/v1/reports/absences/export?from=2026-10-01&to=2026-10-31&format={$format}");

        $response->assertOk()->assertHeader('content-type', $contentType)->assertDownload("absences-20261001-20261031.{$format}");
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
    }

    #[DataProvider('formats')]
    public function test_staff_export_downloads_the_requested_format(string $format, string $contentType): void
    {
        Employee::factory()->count(2)->create();

        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->get("/api/v1/reports/staff/export?format={$format}")
            ->assertOk()
            ->assertHeader('content-type', $contentType);
    }

    /**
     * @return array<string, array{RoleName}>
     */
    public static function rolesWithoutReports(): array
    {
        return ['personnel' => [RoleName::Personnel], 'administrateur' => [RoleName::Administrateur]];
    }

    #[DataProvider('rolesWithoutReports')]
    public function test_reports_return_403_without_permission(RoleName $role): void
    {
        $user = $this->userWithRole($role);

        $this->actingAs($user)->getJson('/api/v1/reports/dashboard')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/reports/absences/export?from=2026-10-01&to=2026-10-31&format=xlsx')->assertForbidden();
    }

    public function test_period_longer_than_a_year_returns_422(): void
    {
        $this->actingAs($this->userWithRole(RoleName::RessourcesHumaines))
            ->getJson('/api/v1/reports/absences?from=2024-01-01&to=2026-01-01')
            ->assertUnprocessable();
    }
}

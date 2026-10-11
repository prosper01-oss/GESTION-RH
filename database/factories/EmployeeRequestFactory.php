<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeRequest>
 */
class EmployeeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_type' => RequestType::Conge,
            'employee_id' => Employee::factory(),
        ];
    }

    /**
     * Demande de congé avec son détail. Le statut se règle avec status() ; seuls les tests
     * le fixent directement, l'application passe toujours par RequestWorkflow.
     *
     * @param  array<string, mixed>  $detail
     */
    public function leave(LeaveType $leaveType, array $detail = []): static
    {
        return $this->afterCreating(function (EmployeeRequest $request) use ($leaveType, $detail): void {
            $request->leaveDetail()->create($detail + [
                'leave_type_id' => $leaveType->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(14)->toDateString(),
                'requested_days' => 5,
            ]);
        });
    }

    public function status(RequestStatus $status): static
    {
        return $this->afterCreating(function (EmployeeRequest $request) use ($status): void {
            $request->forceFill([
                'status' => $status,
                'reference' => $status === RequestStatus::Brouillon ? null : 'OMIT-CG-'.now()->year.'-'.str_pad((string) $request->id, 5, '0', STR_PAD_LEFT),
                'submitted_at' => $status === RequestStatus::Brouillon ? null : now(),
            ])->save();
        });
    }
}

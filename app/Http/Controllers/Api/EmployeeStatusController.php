<?php

namespace App\Http\Controllers\Api;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EmployeeStatusController extends Controller
{
    /**
     * Active ou désactive une fiche (EF-10). Le compte de connexion associé suit le même état.
     */
    public function update(Request $request, Employee $employee): EmployeeResource
    {
        Gate::authorize('update', $employee);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
        ]);
        $status = EmployeeStatus::from($validated['status']);
        $oldStatus = $employee->status;

        DB::transaction(function () use ($employee, $status, $oldStatus): void {
            $employee->status = $status;
            $employee->save();
            $employee->user?->update(['is_active' => $status === EmployeeStatus::Actif]);

            AuditLog::record('employee.status_changed', $employee, ['status' => $oldStatus->value], ['status' => $status->value]);
        });

        return EmployeeResource::make($employee->load('department'));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveBalanceEntryResource;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LeaveBalanceAdjustmentController extends Controller
{
    /**
     * Correction manuelle d'un solde, avec justification obligatoire (EF-26).
     */
    public function store(Request $request, Employee $employee, LeaveBalanceService $balances): JsonResponse
    {
        Gate::authorize(PermissionName::AdjustLeaveBalances->value);

        $validated = $request->validate([
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('has_balance', true)],
            'period_year' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
            'days' => ['required', 'numeric', 'between:-365,365', 'not_in:0', 'multiple_of:0.5'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'leave_type_id.exists' => "Ce type de congé n'est pas tenu en solde.",
            'days.multiple_of' => 'Le nombre de jours doit être un multiple de 0,5.',
            'days.not_in' => 'Le nombre de jours ne peut pas être nul.',
        ]);

        $entry = $balances->adjust(
            $employee,
            LeaveType::findOrFail($validated['leave_type_id']),
            (int) $validated['period_year'],
            (float) $validated['days'],
            $validated['reason'],
            $request->user(),
        );

        AuditLog::record('leave_balance.adjusted', $employee, newValues: $validated);

        return LeaveBalanceEntryResource::make($entry->load('author'))->response()->setStatusCode(201);
    }
}

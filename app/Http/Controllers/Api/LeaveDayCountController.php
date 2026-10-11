<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Services\LeaveDayCounter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class LeaveDayCountController extends Controller
{
    /**
     * Aperçu du nombre de jours décomptés pour une période, selon les règles du type de congé.
     */
    public function show(Request $request, LeaveDayCounter $counter): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'starts_afternoon' => ['boolean'],
            'ends_morning' => ['boolean'],
        ]);

        $leaveType = LeaveType::findOrFail($validated['leave_type_id']);
        $start = CarbonImmutable::parse($validated['start_date']);
        $policy = $leaveType->policyAt($start);
        abort_if($policy === null, 422, "Aucune règle n'est définie pour ce type de congé à cette date.");

        $halfDays = $leaveType->allows_half_day;

        try {
            $days = $counter->count(
                $policy->counting_mode,
                $start,
                CarbonImmutable::parse($validated['end_date']),
                $halfDays && $request->boolean('starts_afternoon'),
                $halfDays && $request->boolean('ends_morning'),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['ends_morning' => $exception->getMessage()]);
        }

        return response()->json(['data' => ['days' => $days, 'counting_mode' => $policy->counting_mode]]);
    }
}

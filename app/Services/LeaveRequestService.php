<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Création et modification des demandes de congé ; le serveur calcule toujours le nombre de jours.
 */
class LeaveRequestService
{
    public function __construct(
        private LeaveDayCounter $counter,
        private RequestWorkflow $workflow,
    ) {}

    /**
     * @param  array{leave_type_id: int, start_date: string, end_date: string, starts_afternoon?: bool, ends_morning?: bool, reason?: ?string}  $data
     */
    public function createDraft(Employee $employee, array $data): EmployeeRequest
    {
        return DB::transaction(function () use ($employee, $data): EmployeeRequest {
            $request = EmployeeRequest::create(['request_type' => RequestType::Conge, 'employee_id' => $employee->id]);
            $request->leaveDetail()->create($this->detailAttributes($data));
            $request->history()->create(['to_status' => RequestStatus::Brouillon, 'actor_id' => auth()->id()]);

            return $request;
        });
    }

    /**
     * @param  array{leave_type_id: int, start_date: string, end_date: string, starts_afternoon?: bool, ends_morning?: bool, reason?: ?string}  $data
     */
    public function updateDraft(EmployeeRequest $request, array $data): EmployeeRequest
    {
        if (! $request->status->isEditableByOwner()) {
            throw new ConflictHttpException('Cette demande ne peut plus être modifiée.');
        }

        $request->leaveDetail->update($this->detailAttributes($data));

        return $request;
    }

    /**
     * Interruption d'un congé en cours : seuls les jours réellement pris sont consommés (section 3.3).
     */
    public function interrupt(EmployeeRequest $request, string $actualEndDate, bool $endsMorning, User $actor): EmployeeRequest
    {
        $detail = $request->leaveDetail()->with('leaveType')->firstOrFail();
        $actualEnd = CarbonImmutable::parse($actualEndDate);

        if ($request->status !== RequestStatus::Finalisee) {
            throw new ConflictHttpException('Seul un congé finalisé peut être interrompu.');
        }
        if ($actualEnd->lt($detail->start_date) || $actualEnd->gte($detail->end_date)) {
            throw ValidationException::withMessages(['actual_end_date' => 'La date de fin réelle doit être comprise entre le début du congé et la veille de la fin prévue.']);
        }

        return DB::transaction(function () use ($request, $detail, $actualEnd, $endsMorning, $actor): EmployeeRequest {
            $detail->update([
                'actual_end_date' => $actualEnd->toDateString(),
                'consumed_days' => $this->countDays($detail->leaveType, $detail->start_date->toDateString(), $actualEnd->toDateString(), $detail->starts_afternoon, $endsMorning),
            ]);

            return $this->workflow->transition($request, RequestStatus::Terminee, $actor, 'Congé interrompu, retour le '.$actualEnd->addDay()->format('d/m/Y'));
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function detailAttributes(array $data): array
    {
        $leaveType = LeaveType::query()->findOrFail($data['leave_type_id']);
        $startsAfternoon = $leaveType->allows_half_day && (bool) ($data['starts_afternoon'] ?? false);
        $endsMorning = $leaveType->allows_half_day && (bool) ($data['ends_morning'] ?? false);

        return [
            'leave_type_id' => $leaveType->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'starts_afternoon' => $startsAfternoon,
            'ends_morning' => $endsMorning,
            'is_urgent' => (bool) ($data['is_urgent'] ?? false),
            'requested_days' => $this->countDays($leaveType, $data['start_date'], $data['end_date'], $startsAfternoon, $endsMorning),
            'reason' => $data['reason'] ?? null,
        ];
    }

    private function countDays(LeaveType $leaveType, string $start, string $end, bool $startsAfternoon, bool $endsMorning): float
    {
        $startDate = CarbonImmutable::parse($start);
        $policy = $leaveType->policyAt($startDate);

        if ($policy === null) {
            throw ValidationException::withMessages(['leave_type_id' => "Aucune règle n'est définie pour ce type de congé à cette date."]);
        }

        try {
            $days = $this->counter->count($policy->counting_mode, $startDate, CarbonImmutable::parse($end), $startsAfternoon, $endsMorning);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['end_date' => $exception->getMessage()]);
        }

        if ($days <= 0) {
            throw ValidationException::withMessages(['end_date' => 'La période ne contient aucun jour décompté (week-end ou jours fériés).']);
        }

        return $days;
    }
}

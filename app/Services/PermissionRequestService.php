<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Demandes de permission (EF-30 à EF-34). La durée est calculée par le serveur, en heures.
 */
class PermissionRequestService
{
    public function __construct(private PermissionQuota $quota) {}

    /**
     * @param  array{permission_type_id: int, start_at: string, end_at: string, reason?: ?string}  $data
     */
    public function createDraft(Employee $employee, array $data): EmployeeRequest
    {
        return DB::transaction(function () use ($employee, $data): EmployeeRequest {
            $request = EmployeeRequest::create(['request_type' => RequestType::Permission, 'employee_id' => $employee->id]);
            $request->permissionDetail()->create($this->detailAttributes($data));
            $request->history()->create(['to_status' => RequestStatus::Brouillon, 'actor_id' => auth()->id()]);

            return $request;
        });
    }

    /**
     * @param  array{permission_type_id: int, start_at: string, end_at: string, reason?: ?string}  $data
     */
    public function updateDraft(EmployeeRequest $request, array $data): EmployeeRequest
    {
        if (! $request->status->isEditableByOwner()) {
            throw new ConflictHttpException('Cette demande ne peut plus être modifiée.');
        }

        $request->permissionDetail->update($this->detailAttributes($data));

        return $request;
    }

    /**
     * Contrôles avant envoi : justificatif et durée maximale (le chevauchement est vérifié par RequestOverlapChecker).
     */
    public function checkBeforeSubmission(EmployeeRequest $request): void
    {
        $detail = $request->permissionDetail;
        $type = $detail->permissionType;

        if (! $type->is_active) {
            throw ValidationException::withMessages(['permission_type_id' => "Cette catégorie de permission n'est plus proposée."]);
        }

        if ($type->requires_attachment && $request->attachments->isEmpty()) {
            throw ValidationException::withMessages(['attachments' => 'Un justificatif est obligatoire pour cette catégorie de permission.']);
        }

        if ($type->requires_details && trim((string) $detail->reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Précisez la nature de la permission.']);
        }

        if ($type->max_duration_hours !== null && (float) $detail->duration_hours > (float) $type->max_duration_hours) {
            throw ValidationException::withMessages(['end_at' => sprintf(
                'La durée dépasse le maximum autorisé pour cette catégorie (%s h).',
                rtrim(rtrim(number_format((float) $type->max_duration_hours, 2, ',', ''), '0'), ','),
            )]);
        }

        $this->checkAnnualQuota($request);
    }

    /**
     * Quota annuel : les jours de l'année (demandes en cours ou accordées) ne dépassent pas le droit annuel.
     */
    private function checkAnnualQuota(EmployeeRequest $request): void
    {
        $annualDays = $this->quota->annualDays();
        if ($annualDays <= 0) {
            return;
        }

        $detail = $request->permissionDetail;

        foreach ($this->quota->daysByYear($detail->start_at, $detail->end_at) as $year => $days) {
            $remaining = max(0, $annualDays - $this->quota->usedDays($request->employee, $year, $request->id));

            if ($days > $remaining) {
                throw ValidationException::withMessages(['end_at' => sprintf(
                    'Quota de permissions %d dépassé : cette demande compte %s j, il reste %s j sur %s j.',
                    $year,
                    $this->formatDays($days),
                    $this->formatDays($remaining),
                    $this->formatDays($annualDays),
                )]);
            }
        }
    }

    private function formatDays(float $days): string
    {
        return rtrim(rtrim(number_format($days, 1, ',', ''), '0'), ',');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function detailAttributes(array $data): array
    {
        $start = CarbonImmutable::parse($data['start_at']);
        $end = CarbonImmutable::parse($data['end_at']);

        return [
            'permission_type_id' => $data['permission_type_id'],
            'start_at' => $start,
            'end_at' => $end,
            'duration_hours' => round($start->diffInMinutes($end) / 60, 2),
            'reason' => $data['reason'] ?? null,
        ];
    }
}

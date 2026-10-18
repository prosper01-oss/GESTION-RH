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

        if ($type->max_duration_hours !== null && (float) $detail->duration_hours > (float) $type->max_duration_hours) {
            throw ValidationException::withMessages(['end_at' => sprintf(
                'La durée dépasse le maximum autorisé pour cette catégorie (%s h).',
                rtrim(rtrim(number_format((float) $type->max_duration_hours, 2, ',', ''), '0'), ','),
            )]);
        }
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

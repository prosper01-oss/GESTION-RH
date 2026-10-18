<?php

namespace App\Http\Resources;

use App\Enums\PermissionName;
use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use App\Models\RequestAttachment;
use App\Models\RequestStatusHistory;
use App\Services\LeaveBalanceService;
use App\Services\RequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeRequest
 */
class EmployeeRequestResource extends JsonResource
{
    /**
     * Demande rechargée avec tout ce que l'écran de détail affiche.
     */
    public static function forDisplay(EmployeeRequest $request): self
    {
        return self::make($request->fresh()->load([
            'employee.department', 'attachments', 'history.actor', 'history.onBehalfOf', ...EmployeeRequest::detailRelations(),
        ]));
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $detail = $this->relationLoaded('leaveDetail') ? $this->leaveDetail : null;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'request_type' => $this->request_type,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'submitted_at' => $this->submitted_at,
            'finalized_at' => $this->finalized_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'matricule' => $this->employee->matricule,
                'nom' => $this->employee->nom,
                'prenom' => $this->employee->prenom,
                'department' => $this->employee->relationLoaded('department') ? $this->employee->department?->name : null,
            ]),
            'leave' => $this->when($detail !== null, fn () => [
                'leave_type' => [
                    'id' => $detail->leaveType->id,
                    'name' => $detail->leaveType->name,
                    'requires_attachment' => $detail->leaveType->requires_attachment,
                    'attachment_deadline_hours' => $detail->leaveType->attachment_deadline_hours,
                    'allows_half_day' => $detail->leaveType->allows_half_day,
                    'min_notice_days' => $detail->leaveType->min_notice_days,
                ],
                'start_date' => $detail->start_date->toDateString(),
                'starts_afternoon' => $detail->starts_afternoon,
                'end_date' => $detail->end_date->toDateString(),
                'ends_morning' => $detail->ends_morning,
                'requested_days' => (float) $detail->requested_days,
                'consumed_days' => $detail->consumed_days === null ? null : (float) $detail->consumed_days,
                'actual_end_date' => $detail->actual_end_date?->toDateString(),
                'reason' => $detail->reason,
                'is_urgent' => $detail->is_urgent,
                // R6 : justificatif attendu après l'envoi, et retard éventuel.
                'attachment_due_at' => $detail->attachment_due_at,
                'attachment_overdue' => $detail->attachment_due_at !== null
                    && $detail->attachment_due_at->isPast()
                    && $this->relationLoaded('attachments') && $this->attachments->isEmpty(),
            ]),
            'permission' => $this->when($this->relationLoaded('permissionDetail') && $this->permissionDetail !== null, fn () => [
                'permission_type' => ['id' => $this->permissionDetail->permissionType->id, 'name' => $this->permissionDetail->permissionType->name, 'requires_attachment' => $this->permissionDetail->permissionType->requires_attachment],
                'start_at' => $this->permissionDetail->start_at,
                'end_at' => $this->permissionDetail->end_at,
                'duration_hours' => (float) $this->permissionDetail->duration_hours,
                'reason' => $this->permissionDetail->reason,
            ]),
            'exit_pass' => $this->when($this->relationLoaded('exitDetail') && $this->exitDetail !== null, fn () => [
                'departure_at' => $this->exitDetail->departure_at,
                'expected_return_at' => $this->exitDetail->expected_return_at,
                'actual_departure_at' => $this->exitDetail->actual_departure_at,
                'actual_return_at' => $this->exitDetail->actual_return_at,
                'destination' => $this->exitDetail->destination,
                'reason' => $this->exitDetail->reason,
                'is_overdue' => $this->exitDetail->isOverdue(),
            ]),
            'can_record_exit' => $this->when(
                $this->relationLoaded('exitDetail') && $this->exitDetail !== null,
                fn () => in_array($this->status, [RequestStatus::Approuvee, RequestStatus::Finalisee], true) && $user->can('recordExit', $this->resource),
            ),
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn (RequestAttachment $attachment): array => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'is_medical' => $attachment->is_medical,
                'can_view' => $user->can('view', $attachment),
                'created_at' => $attachment->created_at,
            ])),
            'history' => $this->whenLoaded('history', fn () => $this->history->map(fn (RequestStatusHistory $entry): array => [
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'to_status_label' => $entry->to_status->label(),
                'actor' => $entry->actor?->name ?? 'Système',
                'on_behalf_of' => $entry->on_behalf_of_id ? $entry->onBehalfOf?->name : null,
                'comment' => $entry->comment,
                'created_at' => $entry->created_at,
            ])),
            'available_transitions' => app(RequestWorkflow::class)->availableTransitions($this->resource, $user),
            'can_edit' => $user->can('update', $this->resource),
            'can_add_attachment' => $user->can('addAttachment', $this->resource),
            'can_delete' => $user->can('delete', $this->resource),
            'can_interrupt' => $user->can('process', $this->resource) && $this->status === RequestStatus::Finalisee && $detail?->start_date->isPast(),
            // Solde de l'agent, pour l'instruction par les RH et la décision du Médecin-Chef.
            'balance' => $this->when(
                $detail !== null && $detail->leaveType->has_balance && ! $this->isOwnedBy($user)
                    && ($user->hasPermission(PermissionName::ReviewRequests) || $user->hasPermission(PermissionName::DecideRequests)),
                fn () => $this->balanceInfo(),
            ),
        ];
    }

    /**
     * @return array{year: int, available: float, requestable: float}
     */
    private function balanceInfo(): array
    {
        $balances = app(LeaveBalanceService::class);
        $detail = $this->leaveDetail;
        $year = $detail->start_date->year;
        $balance = $this->employee->leaveBalances()->where(['leave_type_id' => $detail->leave_type_id, 'period_year' => $year])->first();

        return [
            'year' => $year,
            'available' => $balances->summary($balance)['available'],
            'requestable' => $balances->requestableDays($this->employee, $detail->leaveType, $year, $this->resource),
        ];
    }
}

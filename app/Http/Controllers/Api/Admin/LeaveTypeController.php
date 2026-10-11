<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveLeaveTypeRequest;
use App\Http\Resources\LeaveTypeResource;
use App\Models\AuditLog;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LeaveTypeController extends Controller
{
    /**
     * Tous les types, actifs ou non, avec l'historique de leurs règles.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::ManageLeaveSettings->value);

        return LeaveTypeResource::collection(
            LeaveType::query()
                ->with(['currentPolicy', 'policies' => fn ($query) => $query->orderByDesc('effective_from')->orderByDesc('id')])
                ->orderBy('name')
                ->get()
        );
    }

    public function store(SaveLeaveTypeRequest $request): JsonResponse
    {
        $leaveType = DB::transaction(function () use ($request): LeaveType {
            $leaveType = LeaveType::create($request->typeAttributes());
            $leaveType->policies()->create($request->policyAttributes() + [
                'effective_from' => $request->validated('policy.effective_from') ?? now()->startOfYear()->toDateString(),
                'created_by' => $request->user()->id,
            ]);

            AuditLog::record('leave_type.created', $leaveType, newValues: $request->validated());

            return $leaveType;
        });

        return LeaveTypeResource::make($leaveType->load('currentPolicy', 'policies'))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Modifie le type. Si les règles changent, une nouvelle version datée est créée :
     * l'historique reste disponible pour recalculer les périodes passées.
     */
    public function update(SaveLeaveTypeRequest $request, LeaveType $leaveType): LeaveTypeResource
    {
        DB::transaction(function () use ($request, $leaveType): void {
            $leaveType->fill($request->typeAttributes());
            $typeChanges = $leaveType->getDirty();
            $typeOriginal = array_intersect_key($leaveType->getRawOriginal(), $typeChanges);
            $leaveType->save();

            $newPolicy = $request->policyAttributes();
            $current = $leaveType->currentPolicy;
            $policyChanged = $current === null || $this->policyDiffers($current, $newPolicy);

            if ($policyChanged) {
                $effectiveFrom = $request->validated('policy.effective_from') ?? now()->toDateString();
                $sameDay = $leaveType->policies()->whereDate('effective_from', $effectiveFrom)->first();
                $attributes = $newPolicy + ['created_by' => $request->user()->id];

                $sameDay
                    ? $sameDay->update($attributes)
                    : $leaveType->policies()->create($attributes + ['effective_from' => $effectiveFrom]);
            }

            if ($typeChanges !== [] || $policyChanged) {
                AuditLog::record(
                    'leave_type.updated',
                    $leaveType,
                    $typeOriginal + ($policyChanged && $current ? ['policy' => $this->policyValues($current)] : []),
                    $typeChanges + ($policyChanged ? ['policy' => $newPolicy] : []),
                );
            }
        });

        return LeaveTypeResource::make($leaveType->fresh()->load([
            'currentPolicy',
            'policies' => fn ($query) => $query->orderByDesc('effective_from')->orderByDesc('id'),
        ]));
    }

    /**
     * @param  array<string, mixed>  $newPolicy
     */
    private function policyDiffers(LeavePolicy $current, array $newPolicy): bool
    {
        return $this->policyValues($current) != $newPolicy;
    }

    /**
     * @return array{accrual_rule: string, annual_entitlement: float, counting_mode: string, carry_over_max: ?float}
     */
    private function policyValues(LeavePolicy $policy): array
    {
        return [
            'accrual_rule' => $policy->accrual_rule->value,
            'annual_entitlement' => (float) $policy->annual_entitlement,
            'counting_mode' => $policy->counting_mode->value,
            'carry_over_max' => $policy->carry_over_max === null ? null : (float) $policy->carry_over_max,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\LeavePolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeavePolicy
 */
class LeavePolicyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accrual_rule' => $this->accrual_rule,
            'annual_entitlement' => (float) $this->annual_entitlement,
            'monthly_entitlement' => $this->monthlyEntitlement(),
            'counting_mode' => $this->counting_mode,
            'carry_over_max' => $this->carry_over_max === null ? null : (float) $this->carry_over_max,
            'effective_from' => $this->effective_from->toDateString(),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveType
 */
class LeaveTypeResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'is_paid' => $this->is_paid,
            'has_balance' => $this->has_balance,
            'requires_attachment' => $this->requires_attachment,
            'is_medical' => $this->is_medical,
            'allows_half_day' => $this->allows_half_day,
            'is_active' => $this->is_active,
            'policy' => LeavePolicyResource::make($this->whenLoaded('currentPolicy')),
            'policies' => LeavePolicyResource::collection($this->whenLoaded('policies')),
        ];
    }
}

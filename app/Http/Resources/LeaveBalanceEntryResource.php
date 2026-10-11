<?php

namespace App\Http\Resources;

use App\Models\LeaveBalanceEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveBalanceEntry
 */
class LeaveBalanceEntryResource extends JsonResource
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
            'entry_type' => $this->entry_type,
            'entry_type_label' => $this->entry_type->label(),
            'days' => (float) $this->days,
            'reference_period' => $this->reference_period,
            'reason' => $this->reason,
            'author' => $this->author?->name,
            'created_at' => $this->created_at,
        ];
    }
}

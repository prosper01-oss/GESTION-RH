<?php

namespace App\Http\Resources;

use App\Models\PublicHoliday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PublicHoliday
 */
class PublicHolidayResource extends JsonResource
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
            'date' => $this->date->toDateString(),
            'label' => $this->label,
            'is_recurring' => $this->is_recurring,
        ];
    }
}

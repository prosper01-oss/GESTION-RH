<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
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
            'matricule' => $this->matricule,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'has_photo' => $this->photo_path !== null,
            'department_id' => $this->department_id,
            'department' => DepartmentResource::make($this->whenLoaded('department')),
            'position' => $this->position,
            'hire_date' => $this->hire_date->toDateString(),
            'contract_type' => $this->contract_type,
            'status' => $this->status,
            'phone_pro' => $this->phone_pro,
            'email_pro' => $this->email_pro,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
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
            'manager_employee_id' => $this->manager_employee_id,
            'manager' => $this->whenLoaded('manager', fn () => [
                'id' => $this->manager->id,
                'matricule' => $this->manager->matricule,
                'nom' => $this->manager->nom,
                'prenom' => $this->manager->prenom,
            ]),
            'employees_count' => $this->whenCounted('employees'),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\ExitPassApprover;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'must_change_password' => $this->must_change_password,
            'last_login_at' => $this->last_login_at,
            'employee_id' => $this->employee_id,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'modules' => $this->whenLoaded('directPermissions', fn () => $this->directPermissions->pluck('name')),
            'permissions' => $this->when($this->relationLoaded('roles'), fn () => $this->effectivePermissionNames()),
            // R7 : responsable d'un service ou Médecin-Chef, il voit la file « Sorties à valider ».
            'approves_exits' => $this->when($this->relationLoaded('roles'), fn () => app(ExitPassApprover::class)->approvesExits($this->resource)),
        ];
    }
}

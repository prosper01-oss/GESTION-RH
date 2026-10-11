<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * Entités dont l'administrateur ne voit que les champs modifiés, pas les valeurs :
     * il n'a pas accès par défaut aux données RH (section 2.2 du cahier des charges).
     *
     * @var list<string>
     */
    private const ENTITIES_WITH_HIDDEN_VALUES = ['Employee'];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null),
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'changed_fields' => array_keys($this->new_values ?? $this->old_values ?? []),
            $this->mergeUnless(in_array($this->entity_type, self::ENTITIES_WITH_HIDDEN_VALUES, true), fn () => [
                'old_values' => $this->old_values,
                'new_values' => $this->new_values,
            ]),
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
        ];
    }
}

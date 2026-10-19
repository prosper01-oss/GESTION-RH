<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['permission_type_id', 'start_at', 'end_at', 'duration_hours', 'reason'])]
class PermissionRequestDetail extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'request_id';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'duration_hours' => 'decimal:2',
        ];
    }

    /**
     * Libellé de la catégorie ; pour une catégorie « à préciser », la précision de l'agent
     * remplace la mention entre parenthèses (« Autres : funérailles d'un proche »).
     */
    public function categoryLabel(): string
    {
        $type = $this->permissionType;
        $details = trim((string) $this->reason);

        if (! $type->requires_details || $details === '') {
            return $type->name;
        }

        return trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', $type->name)).' : '.$details;
    }

    /**
     * @return BelongsTo<PermissionType, $this>
     */
    public function permissionType(): BelongsTo
    {
        return $this->belongsTo(PermissionType::class);
    }
}

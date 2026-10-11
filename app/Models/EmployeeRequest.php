<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use Database\Factories\EmployeeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Demande mère (table requests) : congé, permission ou billet de sortie.
 * Le statut ne change que par App\Services\RequestWorkflow.
 */
#[Fillable(['request_type', 'employee_id'])]
class EmployeeRequest extends Model
{
    /** @use HasFactory<EmployeeRequestFactory> */
    use HasFactory;

    protected $table = 'requests';

    protected $attributes = [
        'status' => 'brouillon',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_type' => RequestType::class,
            'status' => RequestStatus::class,
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasOne<LeaveRequestDetail, $this>
     */
    public function leaveDetail(): HasOne
    {
        return $this->hasOne(LeaveRequestDetail::class, 'request_id');
    }

    /**
     * @return HasOne<PermissionRequestDetail, $this>
     */
    public function permissionDetail(): HasOne
    {
        return $this->hasOne(PermissionRequestDetail::class, 'request_id');
    }

    /**
     * @return HasOne<ExitPassDetail, $this>
     */
    public function exitDetail(): HasOne
    {
        return $this->hasOne(ExitPassDetail::class, 'request_id');
    }

    /**
     * Relations de détail à charger pour afficher une demande, quel que soit son type.
     *
     * @return list<string>
     */
    public static function detailRelations(): array
    {
        return ['leaveDetail.leaveType', 'permissionDetail.permissionType', 'exitDetail'];
    }

    /**
     * Le justificatif est-il médical ? Fixé par le type de congé ou de permission.
     */
    public function attachmentsAreMedical(): bool
    {
        return (bool) ($this->leaveDetail?->leaveType->is_medical ?? $this->permissionDetail?->permissionType->is_medical ?? false);
    }

    /**
     * @return HasMany<RequestStatusHistory, $this>
     */
    public function history(): HasMany
    {
        return $this->hasMany(RequestStatusHistory::class, 'request_id');
    }

    /**
     * @return HasMany<RequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class, 'request_id');
    }

    public function isOwnedBy(User $user): bool
    {
        return $user->employee_id !== null && $user->employee_id === $this->employee_id;
    }
}

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

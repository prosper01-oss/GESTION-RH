<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['leave_type_id', 'start_date', 'starts_afternoon', 'end_date', 'ends_morning', 'is_urgent', 'requested_days', 'consumed_days', 'actual_end_date', 'reason', 'attachment_due_at', 'attachment_reminded_at'])]
class LeaveRequestDetail extends Model
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
            'start_date' => 'date',
            'end_date' => 'date',
            'actual_end_date' => 'date',
            'starts_afternoon' => 'boolean',
            'ends_morning' => 'boolean',
            'is_urgent' => 'boolean',
            'attachment_due_at' => 'datetime',
            'attachment_reminded_at' => 'datetime',
            'requested_days' => 'decimal:2',
            'consumed_days' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<EmployeeRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(EmployeeRequest::class, 'request_id');
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}

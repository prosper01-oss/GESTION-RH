<?php

namespace App\Models;

use App\Enums\BalanceEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['leave_balance_id', 'request_id', 'entry_type', 'days', 'reference_period', 'reason', 'created_by'])]
class LeaveBalanceEntry extends Model
{
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_type' => BalanceEntryType::class,
            'days' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<LeaveBalance, $this>
     */
    public function balance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class, 'leave_balance_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

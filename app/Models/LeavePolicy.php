<?php

namespace App\Models;

use App\Enums\AccrualRule;
use App\Enums\CountingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['leave_type_id', 'accrual_rule', 'annual_entitlement', 'counting_mode', 'carry_over_max', 'effective_from', 'created_by'])]
class LeavePolicy extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accrual_rule' => AccrualRule::class,
            'counting_mode' => CountingMode::class,
            'annual_entitlement' => 'decimal:2',
            'carry_over_max' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Droits acquis pour un mois de service, arrondis au centième.
     */
    public function monthlyEntitlement(): float
    {
        return round((float) $this->annual_entitlement / 12, 2);
    }
}

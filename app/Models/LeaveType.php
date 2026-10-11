<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['code', 'name', 'is_paid', 'has_balance', 'requires_attachment', 'is_medical', 'allows_half_day', 'is_active'])]
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'has_balance' => 'boolean',
            'requires_attachment' => 'boolean',
            'is_medical' => 'boolean',
            'allows_half_day' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<LeavePolicy, $this>
     */
    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }

    /**
     * Règles en vigueur aujourd'hui.
     *
     * @return HasOne<LeavePolicy, $this>
     */
    public function currentPolicy(): HasOne
    {
        return $this->hasOne(LeavePolicy::class)->ofMany(
            ['effective_from' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->whereDate('effective_from', '<=', now()->toDateString()),
        );
    }

    /**
     * Règles en vigueur à une date donnée.
     */
    public function policyAt(CarbonInterface $date): ?LeavePolicy
    {
        return $this->policies()
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

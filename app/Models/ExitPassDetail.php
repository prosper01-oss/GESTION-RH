<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['departure_at', 'expected_return_at', 'actual_departure_at', 'actual_return_at', 'destination', 'reason'])]
class ExitPassDetail extends Model
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
            'departure_at' => 'datetime',
            'expected_return_at' => 'datetime',
            'actual_departure_at' => 'datetime',
            'actual_return_at' => 'datetime',
        ];
    }

    /**
     * Sortie en cours dont l'heure de retour prévue est dépassée (EF-42).
     */
    public function isOverdue(): bool
    {
        return $this->actual_departure_at !== null && $this->actual_return_at === null && $this->expected_return_at->isPast();
    }
}

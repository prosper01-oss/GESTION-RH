<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ordre_soins_id', 'service_id', 'qte'])]
class OrdreSoinsLigne extends Model
{
    protected $table = 'pharma_ordre_soins_lignes';

    public function ordre(): BelongsTo
    {
        return $this->belongsTo(OrdreSoins::class, 'ordre_soins_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

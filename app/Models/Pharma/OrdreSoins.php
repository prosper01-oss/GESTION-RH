<?php

namespace App\Models\Pharma;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Soins prescrits par un dentiste à un patient (consultation), à régler en caisse.
 */
#[Fillable(['consultation_id', 'user_id', 'statut', 'note', 'vente_id', 'paye_at'])]
class OrdreSoins extends Model
{
    protected $table = 'pharma_ordre_soins';

    public const EN_ATTENTE = 'en_attente';

    public const PAYE = 'paye';

    public const ANNULE = 'annule';

    protected function casts(): array
    {
        return ['paye_at' => 'datetime'];
    }

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function prescripteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(OrdreSoinsLigne::class, 'ordre_soins_id');
    }

    /** Montant estimé aux prix actuels des services. */
    public function total(): float
    {
        return (float) $this->lignes->sum(fn (OrdreSoinsLigne $ligne): float => (float) $ligne->service?->prix_service * $ligne->qte);
    }
}

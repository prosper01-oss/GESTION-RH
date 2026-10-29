<?php

namespace App\Models\Pharma;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'statut',
    'fond_de_caisse',
    'ouverture_at',
    'fermeture_at',
    'total_ventes',
    'total_encaisse',
    'total_monnaie',
    'theorique',
    'montant_reel',
    'ecart',
    'observations',
])]
class CaisseSession extends Model
{
    protected $table = 'pharma_caisse_sessions';

    public const OUVERTE = 'ouverte';

    public const FERMEE = 'fermee';

    protected function casts(): array
    {
        return [
            'fond_de_caisse' => 'float',
            'total_ventes' => 'float',
            'total_encaisse' => 'float',
            'total_monnaie' => 'float',
            'theorique' => 'float',
            'montant_reel' => 'float',
            'ecart' => 'float',
            'ouverture_at' => 'datetime',
            'fermeture_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class);
    }

    public function estOuverte(): bool
    {
        return $this->statut === self::OUVERTE && $this->fermeture_at === null;
    }
}

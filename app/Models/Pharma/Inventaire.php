<?php

namespace App\Models\Pharma;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Lot B — Séance d'inventaire physique.
 * Statuts : brouillon → valide (verrouillée) → applique (écarts répercutés),
 * ou annule (arrêt sans application).
 */
#[Fillable(['reference', 'date_inventaire', 'statut', 'motif', 'user_id'])]
class Inventaire extends Model
{
    protected $table = 'pharma_inventaires';

    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_VALIDE = 'valide';

    public const STATUT_APPLIQUE = 'applique';

    public const STATUT_ANNULE = 'annule';

    protected function casts(): array
    {
        return [
            'date_inventaire' => 'date:Y-m-d',
        ];
    }

    public function lignes()
    {
        return $this->hasMany(InventaireDetail::class, 'inventaire_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function statutsLibelles(): array
    {
        return [
            self::STATUT_BROUILLON => 'Brouillon',
            self::STATUT_VALIDE => 'Validé',
            self::STATUT_APPLIQUE => 'Appliqué',
            self::STATUT_ANNULE => 'Annulé',
        ];
    }

    public static function statutLibelle(string $statut): string
    {
        return self::statutsLibelles()[$statut] ?? $statut;
    }
}

<?php

namespace App\Models\Pharma;

use App\Models\Employee;
use App\Services\Pharma\Docteurs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'vente_id',
    'nom_patient',
    'sex_patient',
    'age_patient',
    'unite_age',
    'type_docteur',
    'docteur',
    'statut',
    'vu_at',
])]
class Consultation extends Model
{
    protected $table = 'pharma_consultations';

    public const EN_ATTENTE = 'en_attente';

    public const VU = 'vu';

    protected function casts(): array
    {
        return [
            'sex_patient' => 'boolean',
            'unite_age' => 'boolean',
            'vu_at' => 'datetime',
        ];
    }

    public function vente()
    {
        return $this->belongsTo(Vente::class, 'vente_id');
    }

    /** Le docteur est une fiche du personnel RH. */
    public function docteurInfo()
    {
        return $this->belongsTo(Employee::class, 'docteur');
    }

    public function ordres(): HasMany
    {
        return $this->hasMany(OrdreSoins::class);
    }

    public function docteurNom(): string
    {
        return $this->docteurInfo ? Docteurs::nomComplet($this->docteurInfo) : '—';
    }

    public static function sexeLibelle(bool|int $sexe): string
    {
        return ((int) $sexe) === 1 ? 'Homme' : 'Femme';
    }

    public function ageLibelle(): string
    {
        return $this->age_patient.' '.($this->unite_age ? 'ans' : 'mois');
    }
}

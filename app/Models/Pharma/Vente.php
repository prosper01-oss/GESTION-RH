<?php

namespace App\Models\Pharma;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'reference_vente',
    'type',
    'user_id',
    'caisse_session_id',
    'client',
    'montant_brut',
    'montant_paye',
    'monnaie',
    'mode_paiement',
])]
class Vente extends Model
{
    protected $table = 'pharma_ventes';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function caisseSession()
    {
        return $this->belongsTo(CaisseSession::class, 'caisse_session_id');
    }

    public function details()
    {
        return $this->hasMany(VenteDetail::class, 'vente_id');
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class, 'vente_id');
    }

    public function hasConsultations(): bool
    {
        return $this->details()->whereNotNull('consultation_id')->exists();
    }

    public function hasArticles(): bool
    {
        return $this->details()->whereNotNull('article_id')->exists();
    }

    protected function casts(): array
    {
        return [
            'montant_brut' => 'float',
            'montant_paye' => 'float',
            'monnaie' => 'float',
        ];
    }
}

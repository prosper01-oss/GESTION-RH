<?php

namespace App\Models\Pharma;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'vente_id',
    'type',
    'article_id',
    'entree_detail_id',
    'consultation_id',
    'service_id',
    'analyse_id',
    'kit_id',
    'p_u_brut',
    'qte',
    'montant_brut',
    'montant_paye',
])]
class VenteDetail extends Model
{
    protected $table = 'pharma_vente_details';

    public function vente()
    {
        return $this->belongsTo(Vente::class, 'vente_id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function kit()
    {
        return $this->belongsTo(Kit::class, 'kit_id');
    }

    public function lot()
    {
        return $this->belongsTo(EntreeDetail::class, 'entree_detail_id');
    }

    /** Libellé résolu selon la nature de la ligne. */
    public function libelle(): string
    {
        if ($this->consultation_id) {
            return 'Consultation';
        }
        if ($this->article_id && $this->article) {
            return $this->article->designation;
        }
        if ($this->service_id && $this->service) {
            return $this->service->nom_service;
        }
        if ($this->analyse_id) {
            $ids = array_filter(explode(',', (string) $this->analyse_id));

            return Analyse::whereIn('id', $ids)->orderBy('nom')->pluck('nom')->implode(', ');
        }
        if ($this->kit_id && $this->kit) {
            return $this->kit->nom_kit;
        }

        return '—';
    }

    protected function casts(): array
    {
        return [
            'p_u_brut' => 'float',
            'montant_brut' => 'float',
            'montant_paye' => 'float',
        ];
    }
}

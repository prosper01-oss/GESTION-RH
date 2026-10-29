<?php

namespace App\Services\Pharma;

use App\Models\Pharma\Consultation;
use App\Models\Pharma\Vente;
use App\Models\Pharma\VenteDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Ticket PDF thermique (~80 mm) d'une vente : un seul ticket qui regroupe toutes les lignes, séparées en sections
 * (consultation, une section par catégorie de service : Imagerie, Dentisterie, Hébergement…, médicaments, analyses, kits).
 */
class RecuVente
{
    /** Ordre d'impression des natures de ligne. */
    private const TYPES = ['Consultation', 'Service', 'Article', 'Analyse', 'Kit'];

    private const TITRES = [
        'Consultation' => 'CONSULTATION',
        'Article' => 'MÉDICAMENTS',
        'Analyse' => 'ANALYSES MÉDICALES',
        'Kit' => 'KITS',
    ];

    public function pdf(Vente $vente): \Barryvdh\DomPDF\PDF
    {
        $details = VenteDetail::query()->with(['article', 'service.categorie', 'kit', 'lot'])->where('vente_id', $vente->id)->orderBy('id')->get();
        $consultations = Consultation::query()->with('docteurInfo')->where('vente_id', $vente->id)->get();

        // Barres fines (1 module = 2 points d'imprimante à 203 dpi) : le code reste lisible sans occuper toute la largeur.
        $barcode = base64_encode(
            (new BarcodeGeneratorPNG)->getBarcode($vente->reference_vente, BarcodeGeneratorPNG::TYPE_CODE_128, 1, 36)
        );

        $sections = $this->sections($details);

        // Hauteur du ticket : en-tête, sections, lignes, totaux et code-barres (le papier thermique est continu).
        $hauteur = max(340, 250 + 32 * count($sections) + 26 * $details->count() + 14 * $details->where('type', 'Article')->count() + 22 * $consultations->count());

        return Pdf::loadView('pdf.pharma.recu', [
            'vente' => $vente,
            'consultations' => $consultations,
            'sections' => $sections,
            'monnaie' => (float) $vente->monnaie,
            'monnaieArrondie' => Montant::arrondirCentaine((float) $vente->monnaie),
            'barcode' => $barcode,
            'logo' => 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents(resource_path('images/logo-omit-recu.jpg'))),
            'caissier' => $vente->user?->name ?? '—',
        ])->setPaper([0, 0, 226.77, $hauteur], 'portrait'); // 80 mm de large
    }

    /**
     * Regroupe les lignes de la vente en sections d'un même ticket : Consultation, une section par catégorie
     * de service (Imagerie, Dentisterie…), Médicaments, Analyses, Kits. Chaque section a son sous-total.
     *
     * @param  Collection<int, VenteDetail>  $details
     * @return list<array{type: string, titre: string, lignes: Collection<int, VenteDetail>, sous_total: string}>
     */
    public function sections(Collection $details): array
    {
        $sections = [];

        foreach (self::TYPES as $type) {
            $lignes = $details
                ->filter(fn (VenteDetail $l): bool => $type === 'Consultation' ? $l->consultation_id !== null : $l->type === $type)
                ->values();

            if ($lignes->isEmpty()) {
                continue;
            }

            if ($type === 'Service') {
                $lignes->groupBy(fn (VenteDetail $l): string => Str::upper($l->service?->categorie?->nom_cat ?? 'Services'))
                    ->sortKeys()
                    ->each(function (Collection $groupe, string $categorie) use (&$sections, $type): void {
                        $sections[] = $this->section($type, $categorie, $groupe->values());
                    });
            } else {
                $sections[] = $this->section($type, self::TITRES[$type], $lignes);
            }
        }

        return $sections;
    }

    /**
     * @param  Collection<int, VenteDetail>  $lignes
     * @return array{type: string, titre: string, lignes: Collection<int, VenteDetail>, sous_total: string}
     */
    private function section(string $type, string $titre, Collection $lignes): array
    {
        $sousTotal = '0';
        foreach ($lignes as $ligne) {
            $sousTotal = bcadd($sousTotal, (string) $ligne->montant_brut, 2);
        }

        return ['type' => $type, 'titre' => $titre, 'lignes' => $lignes, 'sous_total' => $sousTotal];
    }
}

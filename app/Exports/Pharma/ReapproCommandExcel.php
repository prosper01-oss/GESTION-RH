<?php

namespace App\Exports\Pharma;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Bon de commande fournisseur généré depuis les suggestions de réapprovisionnement.
 * La colonne « À commander » est mise en avant : c'est la donnée essentielle de chaque ligne.
 */
class ReapproCommandExcel implements FromCollection, WithEvents
{
    /** Position de la ligne d'en-têtes du tableau. */
    public const LIGNE_ENTETES = 6;

    /** Colonne de la quantité à commander. */
    public const COL_COMMANDE = 'E';

    /**
     * @param  list<array<string, mixed>>  $lignes
     */
    public function __construct(
        private readonly array $lignes,
        private readonly string $fournisseur,
        private readonly string $periode,
        private readonly int $moisCouverture,
    ) {}

    public function collection(): Collection
    {
        $rows = collect([
            ['BON DE COMMANDE', 'Réapprovisionnement', '', '', '', '', '', '', ''],
            ['Fournisseur', $this->fournisseur !== '' ? $this->fournisseur : 'Non précisé', '', '', '', '', '', '', ''],
            ['Période de consommation (CMM)', $this->periode, '', '', '', '', '', '', ''],
            ['Mois de couverture', (string) $this->moisCouverture, '', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', ''],
            ['N°', 'Référence', 'Désignation', 'Présentation', 'À commander', 'Stock (u.)', 'Seuil', 'CMM / mois', 'Base'],
        ]);

        $total = 0;
        foreach ($this->lignes as $i => $ligne) {
            $rows->push([
                $i + 1,
                $ligne['reference'],
                $ligne['designation'],
                $ligne['presentation'],
                $ligne['manquant'],
                $ligne['stock'],
                $ligne['seuil'],
                $ligne['cmm'],
                $ligne['base'] === 'cmm' ? 'CMM' : 'Cible',
            ]);
            $total += $ligne['manquant'];
        }

        $rows->push(['', '', '', '', '', '', '', '', '']);
        $rows->push(['TOTAL', '', '', 'Quantité totale', $total, '', '', '', '']);

        return $rows;
    }

    /**
     * Mise en forme aux couleurs de l'application (or) : titre, en-têtes, colonne « À commander », total.
     *
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $entetes = self::LIGNE_ENTETES;
                $premiere = $entetes + 1;
                $commande = self::COL_COMMANDE;

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getFont()->getColor()->setARGB('FF7D5C19');
                $sheet->mergeCells('A1:I1');
                $sheet->getStyle('A2:A4')->getFont()->setItalic(true);

                $derniereLigne = $event->sheet->getHighestRow() - 2; // une ligne avant « TOTAL »

                $sheet->getStyle("A{$entetes}:I{$entetes}")->getFont()->setBold(true);
                $sheet->getStyle("A{$entetes}:I{$entetes}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF5ECD0');
                $sheet->getStyle("A{$entetes}:I{$entetes}")->getAlignment()->setHorizontal('center');

                // Barre de filtres sur les données + gel des en-têtes
                $sheet->setAutoFilter("A{$entetes}:I{$derniereLigne}");
                $sheet->freezePane("A{$premiere}");

                // La colonne « À commander » : gras, fond doré, centrée, encadrée.
                $sheet->getColumnDimension($commande)->setAutoSize(false)->setWidth(14);
                $zone = "{$commande}{$premiere}:{$commande}{$derniereLigne}";
                $sheet->getStyle($zone)->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle($zone)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAD7A0');
                $sheet->getStyle($zone)->getAlignment()->setHorizontal('center');
                $sheet->getStyle($zone)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF9C7421');

                if ($derniereLigne >= $premiere) {
                    $sheet->getStyle("H{$premiere}:H{$derniereLigne}")->getNumberFormat()->setFormatCode('0.00');
                }

                $totalLigne = $event->sheet->getHighestRow();
                $sheet->getStyle("A{$totalLigne}:I{$totalLigne}")->getFont()->setBold(true);
                $sheet->getStyle("A{$totalLigne}:I{$totalLigne}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCBD68');
                $sheet->getStyle("{$commande}{$totalLigne}")->getFont()->setBold(true)->setSize(12);

                foreach (range('A', $event->sheet->getHighestColumn()) as $colonne) {
                    if ($colonne !== $commande) {
                        $sheet->getColumnDimension($colonne)->setAutoSize(true);
                    }
                }
            },
        ];
    }
}

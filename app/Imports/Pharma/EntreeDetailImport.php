<?php

namespace App\Imports\Pharma;

use App\Models\Pharma\Article;
use App\Models\Pharma\Pourcentage;
use App\Services\Pharma\LotNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Import des lignes d'entrée (lots).
 *
 * Format attendu :
 * - les 2 premières lignes du fichier sont ignorées (en-têtes)
 * - colonne A (0)  : référence article au format REF-XXXX
 * - colonne F (5)  : quantité « boîtes/unités » (ex : 2/200)
 * - colonne I (8)  : prix d'achat boîte (« Ar », espaces et points tolérés, virgule décimale)
 * - colonne L (11) : date de péremption « Mois Année » en français (ex : Mars 2026), facultatif
 */
class EntreeDetailImport implements ToCollection
{
    private const MOIS_FR = [
        'janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6,
        'juillet' => 7, 'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12,
    ];

    private int $importes = 0;

    /** @var list<array{ligne: int, motif: string}> */
    private array $lignesIgnorees = [];

    public function __construct(private readonly int $entreeIndicesId) {}

    public function collection(Collection $rows): void
    {
        $rows = $rows->values();
        unset($rows[0], $rows[1]); // en-têtes

        $taux = Pourcentage::taux();

        foreach ($rows as $index => $row) {
            if (! isset($row[0]) || trim((string) $row[0]) === '') {
                continue;
            }

            $reference = trim((string) $row[0]);
            $article = $this->articleDepuisReference($reference);

            if (! $article) {
                $this->lignesIgnorees[] = ['ligne' => $index + 1, 'motif' => "Référence inconnue : {$reference}"];

                continue;
            }

            [, $qteUnites] = $this->parseQuantite((string) ($row[5] ?? ''));
            if ($qteUnites < 1) {
                $this->lignesIgnorees[] = ['ligne' => $index + 1, 'motif' => 'Quantité invalide'];

                continue;
            }

            $prixBoite = $this->cleanPrice((string) ($row[8] ?? ''));
            if ($prixBoite <= 0) {
                $this->lignesIgnorees[] = ['ligne' => $index + 1, 'motif' => 'Prix invalide'];

                continue;
            }

            $presentation = max(1, (int) $article->presentation);
            $puAchatUnitaire = bcdiv((string) $prixBoite, (string) $presentation, 2);
            $prixUnitaireVente = bcadd(
                $puAchatUnitaire,
                bcdiv(bcmul($puAchatUnitaire, (string) $taux, 4), '100', 4),
                2
            );

            // RG-PRIX : le PU de vente (brut) fait foi
            DB::table('pharma_entree_details')->insert([
                'entree_indices_id' => $this->entreeIndicesId,
                'article_id' => $article->id,
                'lot' => LotNumber::generer($article->id),
                'source' => 'import',
                'qte_initial' => 0,
                'qte_entree' => $qteUnites,
                'stock_restant_lot' => 0,
                'stock_dispo' => 0,
                'prix_achat_boite' => $prixBoite,
                'prix_unitaire' => $prixUnitaireVente,
                'montant_entree' => bcmul($puAchatUnitaire, (string) $qteUnites, 2),
                'montant_gain_brut' => bcmul($prixUnitaireVente, (string) $qteUnites, 2),
                'nb_lot_dispo' => 0,
                'date_peremption' => $this->parseDatePeremption(trim((string) ($row[11] ?? ''))),
                'isValide' => false,
                'isVendus' => false,
                'etat' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->importes++;
        }
    }

    public function importes(): int
    {
        return $this->importes;
    }

    /**
     * @return list<array{ligne: int, motif: string}>
     */
    public function lignesIgnorees(): array
    {
        return $this->lignesIgnorees;
    }

    /** REF-0008 → Article id 8. */
    private function articleDepuisReference(string $reference): ?Article
    {
        if (! preg_match('/REF-(\d+)/i', $reference, $matches)) {
            return null;
        }

        return Article::query()->where(['id' => (int) $matches[1], 'etat' => 1])->first();
    }

    /**
     * « 2/200 » → [2, 200].
     *
     * @return array{int, int}
     */
    private function parseQuantite(string $valeur): array
    {
        $parts = explode('/', str_replace(' ', '', $valeur));

        return [
            (int) ($parts[0] ?? 0),
            max(0, (int) ($parts[1] ?? 0)),
        ];
    }

    /** « 12 500 Ar » → 12500.0 ; « 1.250,50 » → 1250.50 ; « 12.5 » → 12.5 */
    private function cleanPrice(string $valeur): float
    {
        $valeur = str_ireplace(['Ar', 'ar'], '', $valeur);
        $valeur = preg_replace('/[\s\.](?=\d{3})/', '', $valeur) ?? '';
        $valeur = str_replace(',', '.', $valeur);

        return (float) filter_var($valeur, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /** « Mars 2026 » → 2026-03-01 ; vide → null. */
    private function parseDatePeremption(string $valeur): ?string
    {
        if ($valeur === '') {
            return null;
        }

        $parts = explode(' ', $this->sansAccents(mb_strtolower($valeur)));

        if (count($parts) !== 2 || ! isset(self::MOIS_FR[$parts[0]])) {
            return null;
        }

        $annee = (int) $parts[1];

        return $annee > 1999 ? Carbon::create($annee, self::MOIS_FR[$parts[0]], 1)->toDateString() : null;
    }

    private function sansAccents(string $texte): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte) ?: $texte;
    }
}

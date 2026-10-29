<?php

namespace App\Console\Commands;

use App\Services\Pharma\LegacyDumpReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('omit:pharma-import-catalogue {fichier : Export SQL de l\'ancienne base OmitPharma}')]
#[Description('Pharmacie : importe unités, catégories, analyses, kits et articles depuis un export SQL d\'OmitPharma (ids conservés)')]
class ImporterCataloguePharma extends Command
{
    /**
     * Table d'origine => [table du module, colonnes reprises]. L'ordre respecte les clés étrangères.
     *
     * @var array<string, array{0: string, 1: list<string>, 2: list<string>}>
     */
    private const TABLES = [
        'unites' => ['pharma_unites', ['id', 'nomUnite', 'nomComplet', 'supun', 'created_at', 'updated_at'], ['nomComplet']],
        'categories' => ['pharma_categories', ['id', 'nom_cat', 'etat', 'created_at', 'updated_at'], ['nom_cat']],
        'analyses' => ['pharma_analyses', ['id', 'nom', 'prix', 'etat', 'created_at', 'updated_at'], ['nom']],
        'kits' => ['pharma_kits', ['id', 'nom_kit', 'prix_kit', 'etat', 'created_at', 'updated_at'], ['nom_kit']],
        'articles' => ['pharma_articles', ['id', 'designation', 'presentation', 'unite', 'stock', 'seuil', 'qte_max', 'statut', 'etat', 'created_at', 'updated_at'], ['designation']],
    ];

    /**
     * Les identifiants sont conservés : l'import Excel des entrées retrouve les articles par leur référence REF-XXXX.
     * Relancer la commande met à jour les lignes existantes sans créer de doublons.
     */
    public function handle(): int
    {
        try {
            $dump = LegacyDumpReader::fromFile($this->argument('fichier'));

            $counts = DB::transaction(function () use ($dump): array {
                $counts = [];

                foreach (self::TABLES as $source => [$target, $columns, $trimmed]) {
                    $rows = array_map(fn (array $row): array => $this->prepare($row, $columns, $trimmed), $dump->rows($source));

                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table($target)->upsert($chunk, ['id'], array_values(array_diff($columns, ['id'])));
                    }

                    $counts[$target] = count($rows);
                }

                return $counts;
            });
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($counts as $table => $count) {
            $this->line(sprintf('%-18s %d ligne(s)', $table, $count));
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int|string|null>  $row
     * @param  list<string>  $columns
     * @param  list<string>  $trimmed
     * @return array<string, int|string|null>
     */
    private function prepare(array $row, array $columns, array $trimmed): array
    {
        $prepared = [];

        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            $prepared[$column] = in_array($column, $trimmed, true) && is_string($value) ? trim($value) : $value;
        }

        return $prepared;
    }
}

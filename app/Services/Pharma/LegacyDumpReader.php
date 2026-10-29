<?php

namespace App\Services\Pharma;

use RuntimeException;

/**
 * Lit les instructions INSERT d'un export SQL (phpMyAdmin / mysqldump) de l'ancienne base OmitPharma.
 */
class LegacyDumpReader
{
    public function __construct(private readonly string $sql) {}

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("Fichier introuvable : {$path}");
        }

        return new self((string) file_get_contents($path));
    }

    /**
     * Lignes d'une table, sous la forme colonne => valeur (null pour NULL, entier, décimal en texte ou chaîne).
     *
     * @return list<array<string, int|string|null>>
     */
    public function rows(string $table): array
    {
        $rows = [];

        preg_match_all('/INSERT INTO `'.preg_quote($table, '/').'` \(([^)]*)\) VALUES\s*/', $this->sql, $inserts, PREG_OFFSET_CAPTURE);

        foreach ($inserts[0] as $index => [$header, $offset]) {
            $columns = array_map(fn (string $column): string => trim($column, "` \n"), explode(',', $inserts[1][$index][0]));
            $position = $offset + strlen($header);

            foreach ($this->tuples($position) as $values) {
                if (count($values) !== count($columns)) {
                    throw new RuntimeException("Ligne mal formée dans la table {$table}.");
                }
                $rows[] = array_combine($columns, $values);
            }
        }

        return $rows;
    }

    /**
     * Parcourt les n-uplets « (…) , (…) ; » à partir de $position.
     *
     * @return \Generator<int, list<int|string|null>>
     */
    private function tuples(int $position): \Generator
    {
        $length = strlen($this->sql);

        while ($position < $length) {
            $character = $this->sql[$position];

            if ($character === '(') {
                $values = [];
                $position++;

                while (true) {
                    $values[] = $this->value($position);
                    $character = $this->sql[$position++];

                    if ($character === ')') {
                        break;
                    }
                }

                yield $values;
            } elseif ($character === ',' || ctype_space($character)) {
                $position++;
            } else {
                return; // « ; » : fin de l'instruction
            }
        }
    }

    /**
     * Lit une valeur littérale ; $position s'arrête sur le séparateur (« , » ou « ) ») qui la suit.
     */
    private function value(int &$position): int|string|null
    {
        while (ctype_space($this->sql[$position])) {
            $position++;
        }

        if ($this->sql[$position] === "'") {
            return $this->text($position);
        }

        $start = $position;
        while (! in_array($this->sql[$position], [',', ')'], true)) {
            $position++;
        }
        $literal = trim(substr($this->sql, $start, $position - $start));

        return match (true) {
            strcasecmp($literal, 'NULL') === 0 => null,
            (bool) preg_match('/^-?\d+$/', $literal) => (int) $literal,
            default => $literal,
        };
    }

    private function text(int &$position): string
    {
        static $escapes = ['n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a"];

        $position++; // guillemet ouvrant
        $text = '';

        while (true) {
            $character = $this->sql[$position++];

            if ($character === '\\') {
                $next = $this->sql[$position++];
                $text .= $escapes[$next] ?? $next;
            } elseif ($character === "'") {
                if (($this->sql[$position] ?? '') === "'") {
                    $text .= "'";
                    $position++;
                } else {
                    break;
                }
            } else {
                $text .= $character;
            }
        }

        return $text;
    }
}

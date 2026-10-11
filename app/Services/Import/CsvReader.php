<?php

namespace App\Services\Import;

use Illuminate\Validation\ValidationException;

/**
 * Lecture d'un CSV exporté depuis Excel ou LibreOffice : séparateur « ; » ou « , »,
 * encodage UTF-8 ou Windows-1252, en-têtes insensibles à la casse et aux accents.
 */
class CsvReader
{
    /**
     * @param  list<string>  $requiredColumns
     * @return list<array{line: int, values: array<string, string>}>
     */
    public function read(string $path, array $requiredColumns, int $maxRows = 2000): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if ($lines === false || count($lines) < 2) {
            throw ValidationException::withMessages(['file' => "Le fichier doit contenir une ligne d'en-têtes et au moins une ligne de données."]);
        }

        $delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $headers = array_map(fn (string $header): string => $this->normalize($header), str_getcsv($lines[0], $delimiter, '"', ''));

        $missing = array_diff($requiredColumns, $headers);
        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => 'Colonnes manquantes : '.implode(', ', $missing).'. Utilisez le modèle fourni.']);
        }

        if (count($lines) - 1 > $maxRows) {
            throw ValidationException::withMessages(['file' => "Le fichier dépasse {$maxRows} lignes : découpez-le."]);
        }

        $rows = [];
        foreach (array_slice($lines, 1) as $index => $line) {
            if (trim(str_replace($delimiter, '', $line)) === '') {
                continue;
            }

            $cells = array_map('trim', str_getcsv($line, $delimiter, '"', ''));
            $values = [];
            foreach ($headers as $position => $header) {
                $values[$header] = $cells[$position] ?? '';
            }
            $rows[] = ['line' => $index + 2, 'values' => $values];
        }

        return $rows;
    }

    private function normalize(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = strtr($header, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ô' => 'o', 'î' => 'i', 'ç' => 'c', "'" => '_', ' ' => '_', '-' => '_']);

        return preg_replace('/_+/', '_', $header);
    }
}

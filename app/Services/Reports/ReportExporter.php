<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exports PDF et Excel des rapports (EF-73).
 */
class ReportExporter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     * @param  list<string>  $summary  Lignes de synthèse affichées sous le titre.
     */
    public function xlsx(string $title, array $headers, array $rows, array $summary, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'omit-export-');
        $writer = new Writer;
        $writer->openToFile($path);

        $writer->addRow(Row::fromValuesWithStyle([$title], new Style(fontBold: true, fontSize: 14)));
        foreach ($summary as $line) {
            $writer->addRow(Row::fromValues([$line]));
        }
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValuesWithStyle($headers, new Style(fontBold: true, backgroundColor: 'E8EEF3')));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return response()->download($path, "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     * @param  list<string>  $summary
     */
    public function pdf(string $title, array $headers, array $rows, array $summary, string $filename): Response
    {
        return Pdf::loadView('pdf.report', compact('title', 'headers', 'rows', 'summary'))
            ->setPaper('a4', 'landscape')
            ->download("{$filename}.pdf");
    }
}

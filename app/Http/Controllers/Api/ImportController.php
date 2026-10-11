<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Services\Import\BalanceImporter;
use App\Services\Import\EmployeeImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reprise des données existantes : fiches du personnel et soldes de congés (EF-16, section 12).
 * dry_run=1 vérifie le fichier sans rien enregistrer.
 */
class ImportController extends Controller
{
    public function employees(Request $request, EmployeeImporter $importer): JsonResponse
    {
        Gate::authorize(PermissionName::ManageEmployees->value);

        return $this->respond($importer->import($this->file($request), $request->boolean('dry_run')), $request->boolean('dry_run'));
    }

    public function balances(Request $request, BalanceImporter $importer): JsonResponse
    {
        Gate::authorize(PermissionName::AdjustLeaveBalances->value);

        return $this->respond($importer->import($this->file($request), $request->boolean('dry_run'), $request->user()), $request->boolean('dry_run'));
    }

    /**
     * Modèle CSV à remplir dans Excel (séparateur « ; », UTF-8 avec BOM pour les accents).
     */
    public function template(string $type): StreamedResponse
    {
        [$permission, $columns, $example] = match ($type) {
            'personnel' => [PermissionName::ManageEmployees, EmployeeImporter::COLUMNS, ['OMIT-00125', 'Rasoanaivo', 'Hery', 'Médecine du travail', 'Infirmier', '01/03/2020', 'cdi', '+261 34 00 000 00', 'hery@omit.mg']],
            'soldes' => [PermissionName::AdjustLeaveBalances, BalanceImporter::COLUMNS, ['OMIT-00125', 'CA', (string) now()->year, '12,5', 'Solde au 31/12 du registre papier']],
            default => abort(404),
        };
        Gate::authorize($permission->value);

        return response()->streamDownload(function () use ($columns, $example): void {
            echo "\xEF\xBB\xBF".implode(';', $columns)."\r\n".implode(';', $example)."\r\n";
        }, "modele-import-{$type}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function file(Request $request): string
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], [
            'file.mimes' => 'Le fichier doit être au format CSV (Excel : Enregistrer sous > CSV).',
        ]);

        return $request->file('file')->getRealPath();
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function respond(array $result, bool $dryRun): JsonResponse
    {
        $status = $result['errors'] === [] ? 200 : 422;

        return response()->json(['data' => $result + ['dry_run' => $dryRun]], $status);
    }
}

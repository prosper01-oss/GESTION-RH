<?php

namespace App\Services\Import;

use App\Enums\ContractType;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Services\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Import initial des fiches du personnel (EF-16). Tout ou rien : la moindre ligne en erreur
 * bloque l'import, et le rapport indique chaque erreur avec son numéro de ligne.
 */
class EmployeeImporter
{
    public const COLUMNS = ['matricule', 'nom', 'prenom', 'service', 'fonction', 'date_embauche', 'type_contrat', 'telephone', 'email'];

    public const REQUIRED = ['matricule', 'nom', 'prenom', 'service', 'fonction', 'date_embauche', 'type_contrat'];

    public function __construct(
        private CsvReader $reader,
        private LeaveBalanceService $balances,
    ) {}

    /**
     * @return array{rows: int, created: int, leave_months_credited: int, new_departments: list<string>, errors: list<array{line: int, messages: list<string>}>}
     */
    public function import(string $path, bool $dryRun): array
    {
        $rows = $this->reader->read($path, self::REQUIRED);
        $errors = [];
        $seen = [];
        $prepared = [];

        foreach ($rows as ['line' => $line, 'values' => $values]) {
            $values['date_embauche'] = $this->parseDate($values['date_embauche']);
            $values['type_contrat'] = mb_strtolower($values['type_contrat']);

            $validator = Validator::make($values, [
                'matricule' => ['required', 'string', 'max:30', Rule::unique('employees', 'matricule')],
                'nom' => ['required', 'string', 'max:255'],
                'prenom' => ['required', 'string', 'max:255'],
                'service' => ['required', 'string', 'max:255'],
                'fonction' => ['required', 'string', 'max:255'],
                'date_embauche' => ['required', 'date', 'before_or_equal:today'],
                'type_contrat' => ['required', Rule::enum(ContractType::class)],
                'telephone' => ['nullable', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
            ], [
                'matricule.unique' => 'Le matricule existe déjà.',
                'date_embauche.date' => "La date d'embauche est invalide (format JJ/MM/AAAA).",
                'type_contrat.enum' => 'Type de contrat inconnu (cdi, cdd, stage ou autre).',
            ]);

            $messages = $validator->errors()->all();
            if (isset($seen[$values['matricule']])) {
                $messages[] = "Matricule en double dans le fichier (ligne {$seen[$values['matricule']]}).";
            }
            $seen[$values['matricule']] ??= $line;

            if ($messages !== []) {
                $errors[] = ['line' => $line, 'messages' => $messages];
            } else {
                $prepared[] = $values;
            }
        }

        $existingDepartments = Department::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        $newDepartments = collect($prepared)->pluck('service')->unique(fn ($name) => mb_strtolower($name))
            ->reject(fn ($name) => $existingDepartments->has(mb_strtolower($name)))->values()->all();

        $result = ['rows' => count($rows), 'created' => 0, 'leave_months_credited' => 0, 'new_departments' => $newDepartments, 'errors' => $errors];
        if ($dryRun || $errors !== []) {
            return $result;
        }

        DB::transaction(function () use ($prepared, $newDepartments, $existingDepartments, &$result): void {
            $departmentIds = $existingDepartments->all();
            foreach ($newDepartments as $name) {
                $departmentIds[mb_strtolower($name)] = Department::create(['name' => $name, 'code' => $this->departmentCode($name)])->id;
            }

            foreach ($prepared as $values) {
                $employee = Employee::create([
                    'matricule' => $values['matricule'],
                    'nom' => $values['nom'],
                    'prenom' => $values['prenom'],
                    'department_id' => $departmentIds[mb_strtolower($values['service'])],
                    'position' => $values['fonction'],
                    'hire_date' => $values['date_embauche'],
                    'contract_type' => $values['type_contrat'],
                    'phone_pro' => $values['telephone'] ?? null ?: null,
                    'email_pro' => $values['email'] ?? null ?: null,
                ]);
                $result['created']++;
                // Droits déjà acquis cette année, crédités dès l'import (comme à la création d'une fiche).
                $result['leave_months_credited'] += $this->balances->accrueCurrentYearFor($employee->fresh());
            }

            AuditLog::record('employees.imported', newValues: ['created' => $result['created'], 'new_departments' => $newDepartments]);
        });

        return $result;
    }

    /**
     * Accepte JJ/MM/AAAA (Excel en français) ou AAAA-MM-JJ ; renvoie la chaîne d'origine si illisible.
     */
    private function parseDate(string $value): string
    {
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat("!{$format}", $value);
            } catch (InvalidFormatException) {
                continue;
            }
            // Refuse les dates « corrigées » par PHP, comme le 31/02 transformé en 3 mars.
            if ($date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return $value;
    }

    private function departmentCode(string $name): string
    {
        $base = Str::upper(Str::limit(Str::slug($name, ''), 10, ''));
        $code = $base ?: 'SRV';
        $suffix = 1;

        while (Department::query()->where('code', $code)->exists()) {
            $code = $base.(++$suffix);
        }

        return $code;
    }
}

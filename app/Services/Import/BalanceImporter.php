<?php

namespace App\Services\Import;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Reprise des soldes de congés existants (section 12) : chaque ligne devient une correction
 * justifiée, visible dans l'historique du solde. Tout ou rien.
 */
class BalanceImporter
{
    public const COLUMNS = ['matricule', 'type_conge', 'annee', 'jours', 'motif'];

    public const REQUIRED = ['matricule', 'type_conge', 'annee', 'jours'];

    public function __construct(private CsvReader $reader, private LeaveBalanceService $balances) {}

    /**
     * @return array{rows: int, created: int, errors: list<array{line: int, messages: list<string>}>}
     */
    public function import(string $path, bool $dryRun, User $author): array
    {
        $rows = $this->reader->read($path, self::REQUIRED);
        $employees = Employee::query()->pluck('id', 'matricule');
        $types = LeaveType::query()->where('has_balance', true)->get()->keyBy(fn (LeaveType $type) => mb_strtoupper($type->code));
        $errors = [];
        $prepared = [];

        foreach ($rows as ['line' => $line, 'values' => $values]) {
            $values['jours'] = str_replace([',', ' '], ['.', ''], $values['jours']);
            $values['type_conge'] = mb_strtoupper($values['type_conge']);

            $messages = Validator::make($values, [
                'matricule' => ['required'],
                'type_conge' => ['required'],
                'annee' => ['required', 'integer', 'min:2000', 'max:'.(now()->year + 1)],
                'jours' => ['required', 'numeric', 'between:-365,365', 'multiple_of:0.5'],
                'motif' => ['nullable', 'string', 'max:255'],
            ], ['jours.multiple_of' => 'Le nombre de jours doit être un multiple de 0,5.'])->errors()->all();

            if ($values['matricule'] !== '' && ! $employees->has($values['matricule'])) {
                $messages[] = "Matricule inconnu : {$values['matricule']}.";
            }
            if ($values['type_conge'] !== '' && ! $types->has($values['type_conge'])) {
                $messages[] = "Type de congé inconnu ou non tenu en solde : {$values['type_conge']}.";
            }

            if ($messages !== []) {
                $errors[] = ['line' => $line, 'messages' => $messages];
            } elseif ((float) $values['jours'] != 0.0) {
                $prepared[] = $values;
            }
        }

        $result = ['rows' => count($rows), 'created' => 0, 'errors' => $errors];
        if ($dryRun || $errors !== []) {
            return $result;
        }

        DB::transaction(function () use ($prepared, $employees, $types, $author, &$result): void {
            foreach ($prepared as $values) {
                $this->balances->adjust(
                    Employee::findOrFail($employees[$values['matricule']]),
                    $types[$values['type_conge']],
                    (int) $values['annee'],
                    (float) $values['jours'],
                    ($values['motif'] ?? '') !== '' ? $values['motif'] : 'Reprise des soldes existants',
                    $author,
                );
                $result['created']++;
            }

            AuditLog::record('leave_balances.imported', newValues: ['created' => $result['created']]);
        });

        return $result;
    }
}

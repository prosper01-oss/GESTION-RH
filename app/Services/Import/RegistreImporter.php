<?php

namespace App\Services\Import;

use App\Enums\ContractType;
use App\Enums\EmployeeStatus;
use App\Enums\Sexe;
use App\Enums\SituationFamiliale;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Services\LeaveBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

/**
 * Ajoute au personnel les agents du « Registre des personnels » (.ods ou .xls) qui n'ont pas encore de fiche.
 * Le « N° IM » du registre est le matricule : un agent déjà présent n'est jamais modifié.
 * Le registre ne porte pas de service : il est déduit de la fonction (voir SERVICES).
 */
class RegistreImporter
{
    /**
     * Fonction (sans accents ni majuscules) => service. Les premières règles l'emportent.
     * Les services marqués « nouveau » sont créés s'ils n'existent pas.
     *
     * @var array<string, string>
     */
    public const SERVICES = [
        '/stomato|dentiste/' => 'DENTISTE',
        '/medecin/' => 'MEDECIN',
        '/sage.?femme/' => 'CPN/PF et MATERNITE',
        '/infirmi/' => 'VISITE et URGENCE',
        '/garde|gardien|securite/' => 'SECURITE',
        '/servant/' => 'FEMME DE MENAGE',
        '/informaticien/' => 'SERVICE INFORMATIQUE',
        '/laboratoire/' => 'LABORATOIRE',
        '/secretaire de direction/' => 'SECRETAIRE COMPTABLE',
        '/secretaire medical/' => 'FICHISTE',
        '/secretaire.*pharmacie/' => 'PHARMACIE DETAIL',
        '/responsable des adh/' => "RESPONSABLE D'ADHERENT",
        '/ressources humaines/' => 'RESSOURCES HUMAINES',
        '/chauffeur/' => 'CHAUFFEUR',
        '/maintenance/' => 'AGENT DE MAINTENANCE',
        '/recouvrement/' => 'RECOUVREMENT',
        '/administratif/' => 'ADMINISTRATION',
        '/jardin/' => 'JARDINAGE',
    ];

    /** Colonnes « date de l'engagement » et « date du départ » (en partant de 0). */
    private const DATE_COLUMNS = [9, 10];

    public function __construct(private LeaveBalanceService $balances) {}

    /**
     * @return array{lus: int, existants: int, a_creer: list<array<string, mixed>>, crees: int, nouveaux_services: list<string>, erreurs: list<string>, differences: list<string>}
     */
    public function import(string $path, string $sheet = 'REGISTRE', bool $dryRun = false): array
    {
        $rows = $this->readRows($path, $sheet);
        $existing = Employee::query()->get()->keyBy('matricule');
        $services = Department::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name): array => [mb_strtolower($name) => $id]);

        $result = ['lus' => count($rows), 'existants' => 0, 'a_creer' => [], 'crees' => 0, 'nouveaux_services' => [], 'erreurs' => [], 'differences' => []];
        $matricules = [];

        foreach ($rows as $line => $row) {
            $matricule = $row['matricule'];

            if (isset($matricules[$matricule])) {
                $result['erreurs'][] = "Ligne {$line} : matricule {$matricule} en double dans le fichier.";

                continue;
            }
            $matricules[$matricule] = true;

            [$nom, $prenom] = $this->splitName($row['nom']);

            if ($employee = $existing->get($matricule)) {
                $result['existants']++;
                if (Str::lower(Str::ascii($employee->nom)) !== Str::lower(Str::ascii($nom))) {
                    $result['differences'][] = "Matricule {$matricule} : la fiche porte « {$employee->nom} », le registre « {$nom} ».";
                }

                continue;
            }

            $service = $this->serviceFor($row['fonction']);
            $hireDate = $this->parseDate($row['date_engagement'], full: true);

            if ($service === null) {
                $result['erreurs'][] = "Ligne {$line} : aucun service pour la fonction « {$row['fonction']} » ({$nom}).";

                continue;
            }
            if ($hireDate === null || $hireDate->isFuture()) {
                $result['erreurs'][] = "Ligne {$line} : date d'engagement invalide ou future pour {$nom} (« {$row['date_engagement']} »).";

                continue;
            }

            $result['a_creer'][] = [
                'matricule' => $matricule,
                'nom' => $nom,
                'prenom' => $prenom,
                'cin' => $this->cin($row['cin']),
                'date_naissance' => $this->parseDate($row['naissance'])?->toDateString(),
                'numero_cnasp' => $row['cnaps'] ?: null,
                'adresse' => $row['residence'] ?: null,
                'sexe' => match (Str::upper($row['sexe'])) {
                    'M' => Sexe::Masculin->value,
                    'F' => Sexe::Feminin->value,
                    default => null,
                },
                'situation_familiale' => $this->situation($row['situation']),
                'service' => $service,
                'position' => $row['fonction'],
                'hire_date' => $hireDate->toDateString(),
                'contract_type' => ContractType::Cdi->value,
                'departed' => $row['depart'] !== '' && ($this->parseDate($row['depart'], full: true)?->isPast() ?? false),
            ];
        }

        $result['nouveaux_services'] = collect($result['a_creer'])->pluck('service')->unique()
            ->reject(fn (string $name): bool => $services->has(mb_strtolower($name)))->values()->all();

        if ($dryRun || $result['erreurs'] !== []) {
            return $result;
        }

        DB::transaction(function () use (&$result, $services): void {
            $ids = $services->all();
            foreach ($result['nouveaux_services'] as $name) {
                $ids[mb_strtolower($name)] = Department::create(['name' => $name, 'code' => $this->nextDepartmentCode()])->id;
            }

            foreach ($result['a_creer'] as $data) {
                $employee = Employee::create([
                    'matricule' => $data['matricule'],
                    'nom' => $data['nom'],
                    'prenom' => $data['prenom'],
                    'cin' => $data['cin'],
                    'date_naissance' => $data['date_naissance'],
                    'numero_cnasp' => $data['numero_cnasp'],
                    'adresse' => $data['adresse'],
                    'sexe' => $data['sexe'],
                    'situation_familiale' => $data['situation_familiale'],
                    'department_id' => $ids[mb_strtolower($data['service'])],
                    'position' => $data['position'],
                    'hire_date' => $data['hire_date'],
                    'contract_type' => $data['contract_type'],
                ]);

                AuditLog::record('employee.created', $employee, newValues: ['matricule' => $employee->matricule, 'source' => 'registre']);

                if ($data['departed']) {
                    $employee->forceFill(['status' => EmployeeStatus::Inactif])->save();
                } else {
                    $this->balances->accrueCurrentYearFor($employee->fresh());
                }

                $result['crees']++;
            }
        });

        return $result;
    }

    /** « RANDRIANANDRASANA Félix » → [nom, prénoms] : le nom est la suite de mots en majuscules. */
    public function splitName(string $full): array
    {
        $words = preg_split('/\s+/', trim($full), -1, PREG_SPLIT_NO_EMPTY);
        $nom = [];

        while ($words !== [] && $this->isUppercaseWord($words[0])) {
            $nom[] = array_shift($words);
        }

        if ($nom === []) {
            $nom[] = array_shift($words);
        }

        $nomText = implode(' ', $nom);

        return [$nomText, $words === [] ? $nomText : implode(' ', $words)];
    }

    public function serviceFor(string $function): ?string
    {
        $normalized = Str::lower(Str::ascii($function));

        foreach (self::SERVICES as $pattern => $service) {
            if (preg_match($pattern, $normalized)) {
                return $service;
            }
        }

        return null;
    }

    private function isUppercaseWord(string $word): bool
    {
        return mb_strlen($word) > 1 && ! str_ends_with($word, '.') && $word === mb_strtoupper($word) && preg_match('/\p{L}/u', $word) === 1;
    }

    /** Numéro seul : « 212 091 000 391 du 24/02/95 à Vohipeno » → « 212 091 000 391 ». */
    private function cin(string $value): ?string
    {
        $number = trim(preg_split('/\s+du\s+/i', $value)[0] ?? '');

        return $number === '' ? null : Str::limit($number, 30, '');
    }

    private function situation(string $value): ?string
    {
        return match (Str::lower(Str::ascii(trim($value)))) {
            'cel', 'celibataire' => SituationFamiliale::Celibataire->value,
            'marie', 'mariee' => SituationFamiliale::Marie->value,
            'divorce', 'divorcee' => SituationFamiliale::Divorce->value,
            'veuf', 'veuve' => SituationFamiliale::Veuf->value,
            default => null,
        };
    }

    /**
     * Première date JJ/MM/AA(AA) trouvée. Pour une date de naissance, « Vers 1977 » n'en contient pas : null.
     * Un millésime à deux chiffres est ramené au siècle le plus récent qui ne soit pas dans le futur.
     */
    private function parseDate(string $text, bool $full = false): ?CarbonImmutable
    {
        if (! preg_match('#(\d{1,2})/(\d{1,2})/(\d{4}|\d{2})#', $text, $match)) {
            return null;
        }

        $year = (int) $match[3];
        if ($year < 100) {
            $year += $year <= (int) now()->format('y') ? 2000 : 1900;
        }

        return checkdate((int) $match[2], (int) $match[1], $year)
            ? CarbonImmutable::create($year, (int) $match[2], (int) $match[1])
            : null;
    }

    private function nextDepartmentCode(): string
    {
        $max = Department::query()->pluck('code')->map(fn (string $code): int => (int) preg_replace('/\D/', '', $code))->max() ?? 0;

        return sprintf('SRV-%02d', $max + 1);
    }

    private function cellText(mixed $cell, bool $isDate): string
    {
        if (is_int($cell) || is_float($cell)) {
            return $isDate
                ? CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($cell))->format('d/m/Y')
                : sprintf('%.0f', $cell);
        }

        return trim((string) $cell);
    }

    /**
     * @return array<int, array<string, string>> numéro de ligne => valeurs du registre
     */
    private function readRows(string $path, string $sheet): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Fichier introuvable : {$path}");
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getSheetByName($sheet) ?? throw new RuntimeException("Feuille « {$sheet} » absente du fichier.");

        $rows = [];
        $started = false;

        // Valeurs brutes : les dates arrivent en numéros de série, converties ici en JJ/MM/AAAA.
        foreach ($worksheet->toArray(null, false, false, false) as $index => $cells) {
            $cells = array_map(fn ($cell, int $column): string => $this->cellText($cell, in_array($column, self::DATE_COLUMNS, true)), $cells, array_keys($cells));

            if (! $started) {
                $started = preg_match('/^n.{0,5}\s*im$/i', Str::ascii($cells[0] ?? '')) === 1;

                continue;
            }

            if (($cells[0] ?? '') === '' || ! ctype_digit($cells[0])) {
                continue;
            }

            $rows[$index + 1] = [
                'matricule' => $cells[0],
                'nom' => $cells[1] ?? '',
                'sexe' => $cells[2] ?? '',
                'naissance' => $cells[3] ?? '',
                'cin' => $cells[4] ?? '',
                'cnaps' => $cells[5] ?? '',
                'situation' => $cells[6] ?? '',
                'residence' => $cells[7] ?? '',
                'date_engagement' => $cells[9] ?? '',
                'depart' => $cells[10] ?? '',
                'fonction' => $cells[11] ?? '',
            ];
        }

        return $rows;
    }
}

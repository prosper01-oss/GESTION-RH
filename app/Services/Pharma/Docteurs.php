<?php

namespace App\Services\Pharma;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Docteurs proposés à la caisse : ce sont les fiches du personnel RH actives dont la fonction
 * est celle d'un docteur (« Docteur », « Médecin », « Dentiste »…). Aucune saisie n'est faite côté Pharmacie.
 */
class Docteurs
{
    public const GENERALISTE = 'generaliste';

    public const DENTISTE = 'dentiste';

    /**
     * Docteurs actifs, triés par nom, éventuellement limités à un type de consultation (generaliste | dentiste).
     *
     * @return Collection<int, array{id: int, nom: string, fonction: string, contact: string}>
     */
    public static function liste(?string $type = null): Collection
    {
        return self::docteurs()
            ->when($type, fn (Collection $docteurs) => $docteurs->filter(fn (Employee $employee): bool => self::type($employee) === $type))
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'nom' => self::nomComplet($employee),
                'fonction' => trim($employee->position),
                'contact' => (string) $employee->phone_pro,
            ])
            ->values();
    }

    /** Le docteur actif d'identifiant $id relevant du type de consultation $type, s'il existe. */
    public static function trouver(int $id, string $type): ?Employee
    {
        $employee = Employee::query()->where('status', EmployeeStatus::Actif)->find($id);

        return $employee && self::estDocteur($employee) && self::type($employee) === $type ? $employee : null;
    }

    /**
     * Une fonction est celle d'un docteur si elle contient « docteur », « médecin » ou « dentiste »
     * (y compris une faute de frappe comme « Dentise »), sauf pour les assistants, aides et techniciens.
     */
    public static function estDocteur(Employee $employee): bool
    {
        $fonction = self::normaliser($employee->position);

        if (preg_match('/\b(assistant|assistante|aide|technicien|technicienne|infirmier|infirmiere|secretaire|stagiaire)\b/', $fonction)) {
            return false;
        }

        return (bool) preg_match('/\b(docteur|dr|medecin)\b|\bdentis/', $fonction);
    }

    public static function nomComplet(Employee $employee): string
    {
        return trim("{$employee->nom} {$employee->prenom}");
    }

    /** @return Collection<int, Employee> */
    private static function docteurs(): Collection
    {
        return Employee::query()
            ->where('status', EmployeeStatus::Actif)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get()
            ->filter(fn (Employee $employee): bool => self::estDocteur($employee))
            ->values();
    }

    /** Dentiste si la fonction le dit (« Dentiste », « Médecin Stomato »), généraliste pour tous les autres docteurs. */
    public static function type(Employee $employee): string
    {
        return preg_match('/dentis|stomato/', self::normaliser($employee->position)) ? self::DENTISTE : self::GENERALISTE;
    }

    private static function normaliser(?string $texte): string
    {
        return (string) Str::of((string) $texte)->ascii()->lower()->squish();
    }
}

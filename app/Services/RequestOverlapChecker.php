<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Règle R4 : deux demandes d'un même agent ne peuvent pas se chevaucher, quel que soit leur type
 * (congé, permission, billet de sortie). Sert aussi à vérifier la présence de l'intérimaire d'un congé.
 */
class RequestOverlapChecker
{
    public function ensureNoOverlap(EmployeeRequest $request): void
    {
        $request->loadMissing(EmployeeRequest::detailRelations());
        [$start, $end] = $this->interval($request);

        if ($other = $this->firstConflict($request->employee_id, $start, $end, $request->id)) {
            throw ValidationException::withMessages([$this->field($request) => sprintf(
                'Cette période chevauche la demande %s (%s).',
                $other->reference,
                mb_strtolower($other->request_type->label()),
            )]);
        }
    }

    /**
     * L'intérimaire d'un congé doit être présent pendant toute la période.
     */
    public function ensureInterimAvailable(EmployeeRequest $request): void
    {
        $request->loadMissing(EmployeeRequest::detailRelations());
        $interim = $request->leaveDetail?->interim;
        if ($interim === null) {
            return;
        }

        [$start, $end] = $this->interval($request);

        if ($other = $this->firstConflict($interim->id, $start, $end, $request->id)) {
            throw ValidationException::withMessages(['interim_employee_id' => sprintf(
                '%s %s est absent(e) pendant cette période (%s %s) : choisissez un autre intérimaire.',
                $interim->prenom,
                $interim->nom,
                mb_strtolower($other->request_type->label()),
                $other->reference,
            )]);
        }
    }

    /**
     * Première demande active d'un agent qui recoupe [start, end[.
     */
    private function firstConflict(int $employeeId, CarbonImmutable $start, CarbonImmutable $end, ?int $exceptId): ?EmployeeRequest
    {
        return EmployeeRequest::query()
            ->where('employee_id', $employeeId)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereIn('status', RequestStatus::occupyingPeriod())
            ->with(EmployeeRequest::detailRelations())
            ->get()
            ->first(function (EmployeeRequest $other) use ($start, $end): bool {
                [$otherStart, $otherEnd] = $this->interval($other);

                return $start->lt($otherEnd) && $otherStart->lt($end);
            });
    }

    /**
     * Période occupée par une demande : journées entières pour un congé (demi-journée = midi),
     * heures exactes pour une permission ou une sortie.
     *
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function interval(EmployeeRequest $request): array
    {
        if ($detail = $request->leaveDetail) {
            $start = CarbonImmutable::parse($detail->start_date->toDateString())->setTime($detail->starts_afternoon ? 12 : 0, 0);
            $end = CarbonImmutable::parse($detail->end_date->toDateString())->setTime($detail->ends_morning ? 12 : 24, 0);

            return [$start, $end];
        }

        if ($detail = $request->permissionDetail) {
            return [$detail->start_at->toImmutable(), $detail->end_at->toImmutable()];
        }

        $detail = $request->exitDetail;

        return [$detail->departure_at->toImmutable(), $detail->expected_return_at->toImmutable()];
    }

    private function field(EmployeeRequest $request): string
    {
        return match (true) {
            $request->leaveDetail !== null => 'start_date',
            $request->permissionDetail !== null => 'start_at',
            default => 'departure_at',
        };
    }
}

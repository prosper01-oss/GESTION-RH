<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Billets de sortie (EF-40 à EF-43) : départ et retour réels enregistrés, puis clôture.
 */
class ExitPassService
{
    public function __construct(private RequestWorkflow $workflow) {}

    /**
     * @param  array{departure_at: string, expected_return_at: string, destination: string, reason: string}  $data
     */
    public function createDraft(Employee $employee, array $data): EmployeeRequest
    {
        return DB::transaction(function () use ($employee, $data): EmployeeRequest {
            $request = EmployeeRequest::create(['request_type' => RequestType::Sortie, 'employee_id' => $employee->id]);
            $request->exitDetail()->create($data);
            $request->history()->create(['to_status' => RequestStatus::Brouillon, 'actor_id' => auth()->id()]);

            return $request;
        });
    }

    /**
     * @param  array{departure_at: string, expected_return_at: string, destination: string, reason: string}  $data
     */
    public function updateDraft(EmployeeRequest $request, array $data): EmployeeRequest
    {
        if (! $request->status->isEditableByOwner()) {
            throw new ConflictHttpException('Cette demande ne peut plus être modifiée.');
        }

        $request->exitDetail->update($data);

        return $request;
    }

    public function recordDeparture(EmployeeRequest $request, ?string $at): EmployeeRequest
    {
        $detail = $request->exitDetail;

        if ($request->status !== RequestStatus::Finalisee) {
            throw new ConflictHttpException('Le départ ne peut être enregistré que sur un billet finalisé.');
        }
        if ($detail->actual_departure_at !== null) {
            throw new ConflictHttpException('Le départ est déjà enregistré.');
        }

        $detail->update(['actual_departure_at' => $at ?? now()]);

        return $request;
    }

    /**
     * Enregistre le retour réel et clôture le billet.
     */
    public function recordReturn(EmployeeRequest $request, ?string $at, User $actor): EmployeeRequest
    {
        $detail = $request->exitDetail;

        if ($detail->actual_departure_at === null) {
            throw new ConflictHttpException("Le départ n'a pas encore été enregistré.");
        }

        $returnAt = $at !== null ? CarbonImmutable::parse($at) : now()->toImmutable();
        if ($returnAt->lt($detail->actual_departure_at)) {
            throw ValidationException::withMessages(['actual_return_at' => "L'heure de retour précède l'heure de départ."]);
        }

        return DB::transaction(function () use ($request, $detail, $returnAt, $actor): EmployeeRequest {
            $detail->update(['actual_return_at' => $returnAt]);
            $late = $returnAt->gt($detail->expected_return_at) ? ' (retour après l\'heure prévue)' : '';

            return $this->workflow->transition($request, RequestStatus::Terminee, $actor, 'Retour enregistré à '.$returnAt->format('H:i').$late);
        });
    }
}

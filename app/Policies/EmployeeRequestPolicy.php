<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use App\Models\User;
use App\Services\ExitPassApprover;

class EmployeeRequestPolicy
{
    /**
     * Voir les demandes de tous les agents.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::ReviewRequests) || $user->hasPermission(PermissionName::DecideRequests);
    }

    public function view(User $user, EmployeeRequest $request): bool
    {
        return $request->isOwnedBy($user)
            || $this->viewAny($user)
            || app(ExitPassApprover::class)->canApprove($user, $request);
    }

    /**
     * Un agent rattaché à une fiche peut déposer ses propres demandes.
     * Les RH peuvent en créer pour n'importe quel agent.
     */
    public function create(User $user): bool
    {
        return $user->employee_id !== null || $user->hasPermission(PermissionName::ReviewRequests);
    }

    public function update(User $user, EmployeeRequest $request): bool
    {
        return $request->isOwnedBy($user) && $request->status->isEditableByOwner();
    }

    /**
     * Ajout d'un justificatif : tant que la demande est modifiable, ou après l'envoi tant qu'un
     * justificatif est attendu dans un délai (R6) et que la demande n'est pas close.
     */
    public function addAttachment(User $user, EmployeeRequest $request): bool
    {
        if (! $request->isOwnedBy($user)) {
            return false;
        }

        if ($request->status->isEditableByOwner()) {
            return true;
        }

        $request->loadMissing('leaveDetail');

        return $request->leaveDetail?->attachment_due_at !== null
            && ! in_array($request->status, [RequestStatus::Refusee, RequestStatus::Annulee, RequestStatus::Terminee], true);
    }

    public function delete(User $user, EmployeeRequest $request): bool
    {
        return $request->isOwnedBy($user) && $request->status === RequestStatus::Brouillon;
    }

    /**
     * Gestes des RH sur une demande finalisée : interruption d'un congé, départ et retour d'une sortie.
     */
    public function process(User $user, EmployeeRequest $request): bool
    {
        return ! $request->isOwnedBy($user) && $user->hasPermission(PermissionName::ReviewRequests);
    }

    /**
     * Départ et retour d'une sortie : les RH ou le valideur de la sortie (R7).
     */
    public function recordExit(User $user, EmployeeRequest $request): bool
    {
        return ! $request->isOwnedBy($user)
            && ($user->hasPermission(PermissionName::ReviewRequests) || app(ExitPassApprover::class)->canApprove($user, $request));
    }
}

<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Department;
use App\Models\EmployeeRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Règle R7 : un billet de sortie est validé par le chef direct, c'est-à-dire le responsable du
 * service de l'agent. À défaut (service sans responsable, responsable sans compte actif, ou
 * sortie du responsable lui-même), il est validé par le Médecin-Chef ou son suppléant.
 */
class ExitPassApprover
{
    public function __construct(private RequestRecipients $recipients) {}

    /**
     * Compte du responsable de service qui valide cette sortie, ou null s'il faut se replier sur le Médecin-Chef.
     */
    public function manager(EmployeeRequest $request): ?User
    {
        $request->loadMissing('employee.department.manager.user');
        $manager = $request->employee->department?->manager;

        if ($manager === null || $manager->id === $request->employee_id) {
            return null;
        }

        $user = $manager->user;

        return $user !== null && $user->is_active ? $user : null;
    }

    /**
     * Le valideur est-il le Médecin-Chef (repli) plutôt que le responsable du service ?
     */
    public function usesFallback(EmployeeRequest $request): bool
    {
        return $this->manager($request) === null;
    }

    /**
     * Personnes à prévenir lors de l'envoi de la sortie.
     *
     * @return Collection<int, User>
     */
    public function approvers(EmployeeRequest $request): Collection
    {
        $manager = $this->manager($request);

        return $manager !== null ? collect([$manager]) : $this->recipients->deciders($request);
    }

    public function canApprove(User $user, EmployeeRequest $request): bool
    {
        if ($request->request_type !== RequestType::Sortie || $request->isOwnedBy($user)) {
            return false;
        }

        $manager = $this->manager($request);

        return $manager !== null
            ? $manager->is($user)
            : $user->hasPermission(PermissionName::DecideRequests);
    }

    /**
     * Le compte valide-t-il des sorties : responsable d'au moins un service, ou Médecin-Chef ?
     */
    public function approvesExits(User $user): bool
    {
        return $user->hasPermission(PermissionName::DecideRequests)
            || ($user->employee_id !== null && Department::query()->where('manager_employee_id', $user->employee_id)->exists());
    }

    /**
     * Sorties envoyées qui attendent la validation de ce compte, les plus anciennes d'abord.
     *
     * @return Collection<int, int> Identifiants des demandes.
     */
    public function pendingIdsFor(User $user): Collection
    {
        if (! $this->approvesExits($user)) {
            return collect();
        }

        return EmployeeRequest::query()
            ->where('request_type', RequestType::Sortie)
            ->where('status', RequestStatus::Soumise)
            ->with('employee.department.manager.user')
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (EmployeeRequest $request): bool => $this->canApprove($user, $request))
            ->pluck('id')
            ->values();
    }
}

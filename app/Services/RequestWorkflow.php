<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\RequestStatus as S;
use App\Enums\RequestType;
use App\Models\DocumentSequence;
use App\Models\EmployeeRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Machine d'états des demandes (section 3.3 du cahier des charges).
 * Toute transition absente du tableau est refusée ; chacune est historisée.
 */
class RequestWorkflow
{
    private const OWNER = 'owner';

    private const REVIEWER = 'reviewer';

    private const DECIDER = 'decider';

    /**
     * [de, vers, acteur].
     *
     * @var list<array{S, S, string}>
     */
    private const TRANSITIONS = [
        [S::Brouillon, S::Soumise, self::OWNER],
        [S::Brouillon, S::Annulee, self::OWNER],
        [S::Soumise, S::Annulee, self::OWNER],
        [S::ACorriger, S::Soumise, self::OWNER],
        [S::ACorriger, S::Annulee, self::OWNER],
        [S::Soumise, S::TransmiseMedecin, self::REVIEWER],
        [S::Soumise, S::ACorriger, self::REVIEWER],
        [S::Soumise, S::Refusee, self::REVIEWER],
        [S::RenvoyeeRh, S::TransmiseMedecin, self::REVIEWER],
        [S::RenvoyeeRh, S::ACorriger, self::REVIEWER],
        [S::RenvoyeeRh, S::Refusee, self::REVIEWER],
        [S::TransmiseMedecin, S::Approuvee, self::DECIDER],
        [S::TransmiseMedecin, S::Refusee, self::DECIDER],
        [S::TransmiseMedecin, S::RenvoyeeRh, self::DECIDER],
        [S::Approuvee, S::Finalisee, self::REVIEWER],
        [S::Finalisee, S::Terminee, self::REVIEWER],
        [S::Approuvee, S::Annulee, self::REVIEWER],
        [S::Finalisee, S::Annulee, self::REVIEWER],
    ];

    /**
     * Statuts qui exigent un commentaire.
     *
     * @var list<S>
     */
    private const COMMENT_REQUIRED = [S::ACorriger, S::Refusee, S::RenvoyeeRh];

    public function __construct(private LeaveBalanceService $balances) {}

    /**
     * Applique une transition. $actor null signifie une action du système (clôture automatique).
     */
    public function transition(EmployeeRequest $request, S $to, ?User $actor, ?string $comment = null): EmployeeRequest
    {
        return DB::transaction(function () use ($request, $to, $actor, $comment): EmployeeRequest {
            $request = EmployeeRequest::query()->lockForUpdate()->findOrFail($request->id);
            $request->load('employee', 'leaveDetail.leaveType', 'attachments');
            $from = $request->status;

            $rule = collect(self::TRANSITIONS)->first(fn (array $rule): bool => $rule[0] === $from && $rule[1] === $to);
            if ($rule === null) {
                throw new ConflictHttpException(sprintf('Transition interdite : « %s » vers « %s ».', $from->label(), $to->label()));
            }

            $this->authorize($request, $rule[2], $to, $actor);

            $comment = $comment !== null ? trim($comment) : null;
            if (in_array($to, self::COMMENT_REQUIRED, true) && ($comment === null || $comment === '')) {
                throw ValidationException::withMessages(['comment' => 'Un commentaire est obligatoire pour cette décision.']);
            }

            $this->applyRules($request, $from, $to);

            $request->status = $to;
            if ($to === S::Soumise) {
                $request->reference ??= DocumentSequence::nextReference($request->request_type, now()->year);
                $request->submitted_at ??= now();
            }
            if ($to === S::Finalisee) {
                $request->finalized_at = now();
            }
            if (in_array($to, [S::Terminee, S::Annulee, S::Refusee], true)) {
                $request->closed_at = now();
            }
            $request->save();

            $request->history()->create([
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $actor?->id,
                'comment' => $comment ?: null,
            ]);

            return $request;
        });
    }

    /**
     * Statuts que l'utilisateur peut atteindre depuis le statut actuel (pour l'affichage des boutons).
     *
     * @return list<S>
     */
    public function availableTransitions(EmployeeRequest $request, User $user): array
    {
        return collect(self::TRANSITIONS)
            ->filter(fn (array $rule): bool => $rule[0] === $request->status && $this->actorMatches($request, $rule[2], $user))
            ->map(fn (array $rule): S => $rule[1])
            ->values()
            ->all();
    }

    private function authorize(EmployeeRequest $request, string $actorType, S $to, ?User $actor): void
    {
        // Le système ne fait que la clôture des demandes finalisées.
        if ($actor === null) {
            if ($to !== S::Terminee) {
                throw new AuthorizationException('Action réservée à un utilisateur.');
            }

            return;
        }

        if ($actorType !== self::OWNER && $request->isOwnedBy($actor)) {
            throw new AuthorizationException('Vous ne pouvez pas valider votre propre demande.');
        }

        if (! $this->actorMatches($request, $actorType, $actor)) {
            throw new AuthorizationException("Vous n'êtes pas autorisé à effectuer cette action sur cette demande.");
        }
    }

    private function actorMatches(EmployeeRequest $request, string $actorType, User $user): bool
    {
        return match ($actorType) {
            self::OWNER => $request->isOwnedBy($user),
            self::REVIEWER => ! $request->isOwnedBy($user) && $user->hasPermission(PermissionName::ReviewRequests),
            self::DECIDER => ! $request->isOwnedBy($user) && $user->hasPermission(PermissionName::DecideRequests),
        };
    }

    /**
     * Contrôles métier et effets sur les soldes, propres au type de demande.
     */
    private function applyRules(EmployeeRequest $request, S $from, S $to): void
    {
        if ($request->request_type !== RequestType::Conge) {
            return;
        }

        $detail = $request->leaveDetail;

        match (true) {
            $to === S::Soumise => $this->checkLeaveBeforeSubmission($request),
            $to === S::TransmiseMedecin => $this->balances->ensureRequestable($request),
            $to === S::Approuvee => $this->balances->reserve($request),
            $to === S::Annulee && in_array($from, [S::Approuvee, S::Finalisee], true) => $this->cancelApprovedLeave($request),
            $to === S::Terminee => $this->balances->consume($request, (float) ($detail->consumed_days ?? $detail->requested_days)),
            default => null,
        };
    }

    private function checkLeaveBeforeSubmission(EmployeeRequest $request): void
    {
        $detail = $request->leaveDetail;

        if ($detail->leaveType->requires_attachment && $request->attachments->isEmpty()) {
            throw ValidationException::withMessages(['attachments' => 'Un justificatif est obligatoire pour ce type de congé.']);
        }

        if (! $detail->leaveType->is_active) {
            throw ValidationException::withMessages(['leave_type_id' => "Ce type de congé n'est plus proposé."]);
        }

        // Résolu à l'appel : LeaveRequestService dépend lui-même de ce service.
        app(LeaveRequestService::class)->ensureNoOverlap($request);
        $this->balances->ensureRequestable($request);
    }

    private function cancelApprovedLeave(EmployeeRequest $request): void
    {
        if (! $request->leaveDetail->start_date->isFuture()) {
            throw new ConflictHttpException("Le congé a déjà commencé : il ne peut plus être annulé. Utilisez l'interruption.");
        }

        $this->balances->releaseReservation($request, "Annulation {$request->reference}");
    }
}

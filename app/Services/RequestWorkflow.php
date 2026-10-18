<?php

namespace App\Services;

use App\Enums\PermissionName;
use App\Enums\RequestStatus as S;
use App\Enums\RequestType;
use App\Events\RequestTransitioned;
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

    /** Chef direct de l'agent, ou à défaut le Médecin-Chef (billets de sortie, R7). */
    private const APPROVER = 'approver';

    /** RH ou valideur de la sortie : enregistrement du départ et du retour. */
    private const RECORDER = 'recorder';

    /**
     * [de, vers, acteur].
     *
     * @var list<array{S, S, string}>
     */
    private const TRANSITIONS = [
        [S::Brouillon, S::Soumise, self::OWNER],
        [S::Brouillon, S::Soumise, self::REVIEWER],
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
     * Circuit court des billets de sortie (R7) : validée par le chef direct, la sortie est autorisée.
     * Les deux dernières lignes ne servent qu'aux billets finalisés avant l'adoption de ce circuit.
     *
     * @var list<array{S, S, string}>
     */
    private const EXIT_PASS_TRANSITIONS = [
        [S::Brouillon, S::Soumise, self::OWNER],
        [S::Brouillon, S::Soumise, self::REVIEWER],
        [S::Brouillon, S::Annulee, self::OWNER],
        [S::Soumise, S::Annulee, self::OWNER],
        [S::ACorriger, S::Soumise, self::OWNER],
        [S::ACorriger, S::Annulee, self::OWNER],
        [S::Soumise, S::Approuvee, self::APPROVER],
        [S::Soumise, S::Refusee, self::APPROVER],
        [S::Soumise, S::ACorriger, self::APPROVER],
        [S::Approuvee, S::Terminee, self::RECORDER],
        [S::Approuvee, S::Annulee, self::REVIEWER],
        [S::Finalisee, S::Terminee, self::RECORDER],
        [S::Finalisee, S::Annulee, self::REVIEWER],
    ];

    /**
     * Statuts qui exigent un commentaire.
     *
     * @var list<S>
     */
    private const COMMENT_REQUIRED = [S::ACorriger, S::Refusee, S::RenvoyeeRh];

    public function __construct(
        private LeaveBalanceService $balances,
        private RequestOverlapChecker $overlaps,
        private ExitPassApprover $exitPassApprover,
    ) {}

    /**
     * Applique une transition. $actor null signifie une action du système (clôture automatique).
     */
    public function transition(EmployeeRequest $request, S $to, ?User $actor, ?string $comment = null): EmployeeRequest
    {
        return DB::transaction(function () use ($request, $to, $actor, $comment): EmployeeRequest {
            $request = EmployeeRequest::query()->lockForUpdate()->findOrFail($request->id);
            $request->load(['employee', 'attachments', ...EmployeeRequest::detailRelations()]);
            $from = $request->status;

            $candidates = collect($this->transitionsFor($request))
                ->filter(fn (array $rule): bool => $rule[0] === $from && $rule[1] === $to);

            if ($candidates->isEmpty()) {
                throw new ConflictHttpException(sprintf('Transition interdite : « %s » vers « %s ».', $from->label(), $to->label()));
            }

            // Choisir la règle que l'acteur peut exécuter, sinon prendre la première pour l'erreur d'autorisation.
            $rule = $candidates->first(fn (array $rule): bool => $actor === null || $this->actorMatches($request, $rule[2], $actor))
                ?? $candidates->first();

            $this->authorize($request, $rule[2], $to, $actor);
            $onBehalfOf = $this->delegatorFor($rule[2], $actor, $request);

            $comment = $comment !== null ? trim($comment) : null;
            if (in_array($to, self::COMMENT_REQUIRED, true) && ($comment === null || $comment === '')) {
                throw ValidationException::withMessages(['comment' => 'Un commentaire est obligatoire pour cette décision.']);
            }

            if ($to === S::Soumise) {
                $this->overlaps->ensureNoOverlap($request);
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

            $history = $request->history()->create([
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $actor?->id,
                'on_behalf_of_id' => $onBehalfOf,
                'comment' => $comment ?: null,
            ]);

            RequestTransitioned::dispatch($request, $from, $to, $actor?->id, $history->comment);

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
        return collect($this->transitionsFor($request))
            ->filter(fn (array $rule): bool => $rule[0] === $request->status && $this->actorMatches($request, $rule[2], $user))
            ->map(fn (array $rule): S => $rule[1])
            ->values()
            ->all();
    }

    /**
     * @return list<array{S, S, string}>
     */
    private function transitionsFor(EmployeeRequest $request): array
    {
        return $request->request_type === RequestType::Sortie ? self::EXIT_PASS_TRANSITIONS : self::TRANSITIONS;
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

        // Un suppléant ne décide pas des demandes de la personne qui l'a désigné (section 2.2).
        $delegatorId = $this->delegatorFor($actorType, $actor, $request);
        if ($delegatorId !== null && $request->employee->user?->id === $delegatorId) {
            throw new AuthorizationException('En tant que suppléant, vous ne pouvez pas traiter les demandes de la personne que vous remplacez.');
        }
    }

    /**
     * Personne remplacée lorsque l'acteur agit au titre d'une suppléance.
     */
    private function delegatorFor(string $actorType, ?User $actor, ?EmployeeRequest $request = null): ?int
    {
        return match (true) {
            $actor === null => null,
            $actorType === self::REVIEWER => $actor->delegatorFor(PermissionName::ReviewRequests),
            $actorType === self::DECIDER => $actor->delegatorFor(PermissionName::DecideRequests),
            // Le Médecin-Chef ne valide une sortie qu'en repli : son suppléant agit alors en son nom.
            $actorType === self::APPROVER && $request !== null && $this->exitPassApprover->usesFallback($request) => $actor->delegatorFor(PermissionName::DecideRequests),
            default => null,
        };
    }

    private function actorMatches(EmployeeRequest $request, string $actorType, User $user): bool
    {
        return match ($actorType) {
            self::OWNER => $request->isOwnedBy($user),
            self::REVIEWER => ! $request->isOwnedBy($user) && $user->hasPermission(PermissionName::ReviewRequests),
            self::DECIDER => ! $request->isOwnedBy($user) && $user->hasPermission(PermissionName::DecideRequests),
            self::APPROVER => $this->exitPassApprover->canApprove($user, $request),
            self::RECORDER => ! $request->isOwnedBy($user)
                && ($user->hasPermission(PermissionName::ReviewRequests) || $this->exitPassApprover->canApprove($user, $request)),
        };
    }

    /**
     * Contrôles métier et effets sur les soldes, propres au type de demande.
     */
    private function applyRules(EmployeeRequest $request, S $from, S $to): void
    {
        match ($request->request_type) {
            RequestType::Conge => $this->applyLeaveRules($request, $from, $to),
            RequestType::Permission => $to === S::Soumise ? app(PermissionRequestService::class)->checkBeforeSubmission($request) : null,
            RequestType::Sortie => $this->applyExitPassRules($request, $to),
        };
    }

    private function applyExitPassRules(EmployeeRequest $request, S $to): void
    {
        $detail = $request->exitDetail;

        if ($to === S::Soumise && $detail->expected_return_at->lte($detail->departure_at)) {
            throw ValidationException::withMessages(['expected_return_at' => "L'heure de retour prévue doit suivre l'heure de départ."]);
        }

        $maxHours = (float) config('omit.exit_pass_max_hours');
        if ($to === S::Soumise && $maxHours > 0 && $detail->departure_at->diffInMinutes($detail->expected_return_at) > $maxHours * 60) {
            throw ValidationException::withMessages(['expected_return_at' => sprintf('Une sortie dure au plus %s h.', rtrim(rtrim(number_format($maxHours, 2, ',', ''), '0'), ','))]);
        }

        if ($to === S::Terminee && $detail->actual_return_at === null) {
            throw new ConflictHttpException("Enregistrez l'heure de retour pour clôturer le billet.");
        }

        if ($to === S::Annulee && $detail->actual_departure_at !== null) {
            throw new ConflictHttpException('La sortie a déjà eu lieu : le billet ne peut plus être annulé.');
        }
    }

    private function applyLeaveRules(EmployeeRequest $request, S $from, S $to): void
    {
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
        $leaveType = $detail->leaveType;

        if (! $leaveType->is_active) {
            throw ValidationException::withMessages(['leave_type_id' => "Ce type de congé n'est plus proposé."]);
        }

        $this->ensureNoticeRespected($request);
        $this->scheduleAttachment($request);
        $this->balances->ensureRequestable($request);
    }

    /**
     * R8 : un congé se dépose au moins N jours avant son premier jour, sauf urgence motivée.
     * Le délai court depuis le premier envoi, pour ne pas pénaliser une demande renvoyée à corriger.
     */
    private function ensureNoticeRespected(EmployeeRequest $request): void
    {
        $detail = $request->leaveDetail;
        $noticeDays = $detail->leaveType->min_notice_days;

        if ($detail->is_urgent) {
            if (trim((string) $detail->reason) === '') {
                throw ValidationException::withMessages(['reason' => "Indiquez le motif de l'urgence."]);
            }

            return;
        }

        $submittedOn = ($request->submitted_at ?? now())->toImmutable()->startOfDay();
        if ($noticeDays > 0 && $detail->start_date->lt($submittedOn->addDays($noticeDays))) {
            throw ValidationException::withMessages(['start_date' => sprintf(
                "Une demande de %s doit être déposée au moins %d jours à l'avance. En cas d'urgence, cochez « Urgence » et indiquez le motif.",
                mb_strtolower($detail->leaveType->name),
                $noticeDays,
            )]);
        }
    }

    /**
     * R6 : justificatif exigé avant l'envoi, ou attendu dans un délai après le premier jour d'arrêt.
     */
    private function scheduleAttachment(EmployeeRequest $request): void
    {
        $detail = $request->leaveDetail;
        $leaveType = $detail->leaveType;

        if (! $leaveType->requires_attachment || $request->attachments->isNotEmpty()) {
            $detail->update(['attachment_due_at' => null]);

            return;
        }

        if ($leaveType->attachment_deadline_hours === null) {
            throw ValidationException::withMessages(['attachments' => 'Un justificatif est obligatoire pour ce type de congé.']);
        }

        $detail->update([
            'attachment_due_at' => $detail->start_date->toImmutable()->startOfDay()->addHours($leaveType->attachment_deadline_hours),
        ]);
    }

    private function cancelApprovedLeave(EmployeeRequest $request): void
    {
        if (! $request->leaveDetail->start_date->isFuture()) {
            throw new ConflictHttpException("Le congé a déjà commencé : il ne peut plus être annulé. Utilisez l'interruption.");
        }

        $this->balances->releaseReservation($request, "Annulation {$request->reference}");
    }
}

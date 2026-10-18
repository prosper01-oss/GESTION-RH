<?php

namespace App\Listeners;

use App\Enums\RequestStatus as S;
use App\Enums\RequestType;
use App\Events\RequestTransitioned;
use App\Models\EmployeeRequest;
use App\Models\User;
use App\Notifications\RequestUpdate;
use App\Services\ExitPassApprover;
use App\Services\RequestRecipients;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Prévient les bonnes personnes à chaque étape du circuit (EF-60).
 */
class SendRequestNotifications
{
    public function __construct(
        private RequestRecipients $recipients,
        private ExitPassApprover $exitPassApprover,
    ) {}

    public function handle(RequestTransitioned $event): void
    {
        $request = $event->request->loadMissing('employee');
        $reference = $request->reference ?? 'brouillon';
        $agent = "{$request->employee->prenom} {$request->employee->nom}";
        $type = mb_strtolower($request->request_type->label());
        $reason = $event->comment ? " Motif : {$event->comment}" : '';
        $owner = $this->recipients->owner($request);

        if ($request->request_type === RequestType::Sortie) {
            $this->exitPass($event, $request, $owner, $reference, $agent, $reason);

            return;
        }

        match ($event->to) {
            S::Soumise => $this->send($this->recipients->reviewers($request), $request,
                'Demande à contrôler', "{$agent} a envoyé une demande de {$type} ({$reference}) à contrôler."),
            S::TransmiseMedecin => $this->send($this->recipients->deciders($request), $request,
                'Demande à décider', "La demande de {$type} {$reference} de {$agent} attend votre décision.", sms: true),
            S::RenvoyeeRh => $this->send($this->recipients->reviewers($request), $request,
                'Demande renvoyée aux RH', "Le Médecin-Chef renvoie la demande {$reference} de {$agent} pour complément.{$reason}"),
            S::ACorriger => $this->send($owner, $request,
                'Demande à corriger', "Votre demande de {$type} {$reference} est à corriger.{$reason}", sms: true),
            S::Approuvee => $this->approved($owner, $request, $type, $reference, $agent),
            S::Refusee => $this->send($owner, $request,
                'Demande refusée', "Votre demande de {$type} {$reference} est refusée.{$reason}", sms: true),
            S::Finalisee => $this->send($owner, $request,
                'Autorisation prête', "L'autorisation de votre demande de {$type} {$reference} est enregistrée et peut être imprimée."),
            S::Annulee => in_array($event->from, [S::Approuvee, S::Finalisee], true) && $event->actorId !== $owner?->id
                ? $this->send($owner, $request, 'Demande annulée', "Votre demande de {$type} {$reference} a été annulée par les RH.{$reason}", sms: true)
                : null,
            default => null,
        };
    }

    /**
     * Billets de sortie (R7) : le chef direct valide, l'agent est prévenu, les RH sont informées.
     */
    private function exitPass(RequestTransitioned $event, EmployeeRequest $request, ?User $owner, string $reference, string $agent, string $reason): void
    {
        match ($event->to) {
            S::Soumise => $this->send($this->exitPassApprover->approvers($request), $request,
                'Sortie à valider', "{$agent} demande un billet de sortie ({$reference}) à valider.", sms: true),
            S::Approuvee => $this->exitPassApproved($owner, $request, $reference, $agent),
            S::ACorriger => $this->send($owner, $request,
                'Sortie à corriger', "Votre billet de sortie {$reference} est à corriger.{$reason}", sms: true),
            S::Refusee => $this->send($owner, $request,
                'Sortie refusée', "Votre billet de sortie {$reference} est refusé.{$reason}", sms: true),
            S::Annulee => in_array($event->from, [S::Approuvee, S::Finalisee], true) && $event->actorId !== $owner?->id
                ? $this->send($owner, $request, 'Sortie annulée', "Votre billet de sortie {$reference} a été annulé par les RH.{$reason}", sms: true)
                : null,
            default => null,
        };
    }

    private function exitPassApproved(?User $owner, EmployeeRequest $request, string $reference, string $agent): void
    {
        $this->send($owner, $request, 'Sortie autorisée', "Votre billet de sortie {$reference} est autorisé.", sms: true);
        $this->send($this->recipients->reviewers($request), $request, 'Sortie autorisée', "Pour information : la sortie {$reference} de {$agent} est autorisée.");
    }

    private function approved(?User $owner, $request, string $type, string $reference, string $agent): void
    {
        $this->send($owner, $request, 'Demande approuvée', "Votre demande de {$type} {$reference} est approuvée.", sms: true);

        // L'agent qui assure l'intérim est prévenu de la période à couvrir.
        $interimUser = $request->leaveDetail?->interim?->user;
        if ($interimUser !== null && $interimUser->is_active) {
            $detail = $request->leaveDetail;
            $this->send($interimUser, $request, 'Intérim à assurer', sprintf(
                "Vous assurerez l'intérim de %s du %s au %s (reprise le %s), demande %s.",
                $agent,
                $detail->start_date->format('d/m/Y'),
                $detail->end_date->format('d/m/Y'),
                $detail->return_date?->format('d/m/Y') ?? '—',
                $reference,
            ));
        }
        $this->send($this->recipients->reviewers($request), $request, 'Demande à finaliser', "La demande {$reference} de {$agent} est approuvée : elle est à finaliser.");
    }

    /**
     * @param  User|Collection<int, User>|null  $users
     */
    private function send(User|Collection|null $users, $request, string $title, string $message, bool $sms = false): void
    {
        $users = $users instanceof User ? collect([$users]) : ($users ?? collect());

        if ($users->isNotEmpty()) {
            Notification::send($users, new RequestUpdate($request, $title, $message, $sms));
        }
    }
}

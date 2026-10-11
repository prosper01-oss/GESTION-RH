<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use App\Notifications\PendingRequestsReminder;
use App\Services\RequestRecipients;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('omit:remind-pending-requests')]
#[Description('Rappelle aux RH et au Médecin-Chef les demandes en attente depuis plus de N jours (OMIT_REMINDER_DAYS)')]
class RemindPendingRequests extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RequestRecipients $recipients): int
    {
        $days = config('omit.reminder_days');
        $sent = 0;

        $queues = [
            ['à contrôler', [RequestStatus::Soumise, RequestStatus::RenvoyeeRh], $recipients->reviewers()],
            ['à décider', [RequestStatus::TransmiseMedecin], $recipients->deciders()],
        ];

        foreach ($queues as [$label, $statuses, $users]) {
            $requests = $this->stale($statuses, $days);

            foreach ($users as $user) {
                // Une personne n'est jamais relancée pour ses propres demandes.
                $references = $requests->reject(fn (EmployeeRequest $request): bool => $request->isOwnedBy($user))->pluck('reference')->all();

                if ($references !== []) {
                    $user->notify(new PendingRequestsReminder($label, $references, $days));
                    $sent++;
                }
            }
        }

        $this->info("{$sent} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }

    /**
     * Demandes dont la dernière transition remonte à plus de $days jours.
     *
     * @param  list<RequestStatus>  $statuses
     * @return Collection<int, EmployeeRequest>
     */
    private function stale(array $statuses, int $days): Collection
    {
        return EmployeeRequest::query()
            ->whereIn('status', $statuses)
            ->where('updated_at', '<', now()->subDays($days))
            ->orderBy('updated_at')
            ->get();
    }
}

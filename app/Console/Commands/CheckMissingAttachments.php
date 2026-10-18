<?php

namespace App\Console\Commands;

use App\Enums\RequestStatus;
use App\Models\EmployeeRequest;
use App\Notifications\RequestUpdate;
use App\Services\RequestRecipients;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('omit:check-missing-attachments')]
#[Description('Alerte l\'agent et les RH quand un justificatif attendu (R6, par exemple 48 h pour un congé maladie) n\'a pas été fourni')]
class CheckMissingAttachments extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RequestRecipients $recipients): int
    {
        $alerted = 0;

        EmployeeRequest::query()
            ->whereNotIn('status', [RequestStatus::Brouillon, RequestStatus::Refusee, RequestStatus::Annulee, RequestStatus::Terminee])
            ->whereDoesntHave('attachments')
            ->whereHas('leaveDetail', fn ($query) => $query
                ->whereNotNull('attachment_due_at')
                ->where('attachment_due_at', '<', now())
                ->whereNull('attachment_reminded_at'))
            ->with('employee', 'leaveDetail.leaveType')
            ->orderBy('id')
            ->each(function (EmployeeRequest $request) use ($recipients, &$alerted): void {
                $detail = $request->leaveDetail;
                $agent = "{$request->employee->prenom} {$request->employee->nom}";
                $due = $detail->attachment_due_at->format('d/m/Y à H:i');

                if ($owner = $recipients->owner($request)) {
                    $owner->notify(new RequestUpdate($request, 'Justificatif manquant',
                        "Le justificatif de votre {$this->lower($detail->leaveType->name)} {$request->reference} était attendu avant le {$due}. Ajoutez-le dès que possible.", true));
                }

                $reviewers = $recipients->reviewers($request);
                if ($reviewers->isNotEmpty()) {
                    Notification::send($reviewers, new RequestUpdate($request, 'Justificatif manquant',
                        "{$agent} n'a pas fourni le justificatif de la demande {$request->reference}, attendu avant le {$due}."));
                }

                $detail->update(['attachment_reminded_at' => now()]);
                $alerted++;
            });

        $this->info("{$alerted} demande(s) sans justificatif signalée(s).");

        return self::SUCCESS;
    }

    private function lower(string $text): string
    {
        return mb_strtolower($text);
    }
}

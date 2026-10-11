<?php

namespace App\Notifications;

use App\Models\EmployeeRequest;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification d'une étape du circuit (EF-60 à EF-62).
 * L'alerte dans l'application part immédiatement ; l'email et le SMS passent par la file d'attente
 * et sont réessayés pendant trois jours en cas de coupure d'Internet (EF-64).
 */
class RequestUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Intervalles entre deux tentatives (secondes).
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public EmployeeRequest $request,
        public string $title,
        public string $message,
        public bool $sendSms = false,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return array_values(array_filter([
            'database',
            $notifiable->notify_by_email ? 'mail' : null,
            $this->sendSms && $notifiable->notify_by_sms ? SmsChannel::class : null,
        ]));
    }

    /**
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(3);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[OMIT] {$this->title}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->message)
            ->action('Voir la demande', url("/demandes/{$this->request->id}"))
            ->salutation('Application de gestion du personnel de l\'OMIT');
    }

    public function toSms(User $notifiable): string
    {
        return mb_substr("OMIT : {$this->message}", 0, 300);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'request_id' => $this->request->id,
            'reference' => $this->request->reference,
            'title' => $this->title,
            'message' => $this->message,
        ];
    }
}

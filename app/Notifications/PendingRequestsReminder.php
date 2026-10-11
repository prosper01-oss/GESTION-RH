<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Rappel des demandes en attente depuis trop longtemps (section 3.5).
 */
class PendingRequestsReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 3600];

    /**
     * @param  list<string>  $references
     */
    public function __construct(public string $queueLabel, public array $references, public int $days) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return array_values(array_filter(['database', $notifiable->notify_by_email ? 'mail' : null]));
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
        return now()->addDays(1);
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[OMIT] '.$this->title())
            ->greeting("Bonjour {$notifiable->name},")
            ->line($this->message())
            ->action('Voir les demandes à traiter', url('/a-traiter'))
            ->salutation("Application de gestion du personnel de l'OMIT");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return ['request_id' => null, 'reference' => null, 'title' => $this->title(), 'message' => $this->message(), 'link' => '/a-traiter'];
    }

    private function title(): string
    {
        return 'Rappel : demandes en attente';
    }

    private function message(): string
    {
        $count = count($this->references);

        return "{$count} demande(s) {$this->queueLabel} depuis plus de {$this->days} jour(s) : ".implode(', ', array_slice($this->references, 0, 10)).($count > 10 ? '…' : '').'.';
    }
}

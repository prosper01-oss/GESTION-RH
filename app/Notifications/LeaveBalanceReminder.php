<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Rappel annuel du solde de congés disponible (envoyé le 1er janvier de chaque année).
 *
 * @param  array<int, array{name: string, available: float}>  $balances
 */
class LeaveBalanceReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    /**
     * @param  array<int, array{name: string, available: float}>  $balances
     */
    public function __construct(public array $balances, public int $year) {}

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
        return now()->addDays(3);
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[OMIT] Solde de congés disponible — {$this->year}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Voici votre solde de congés disponible pour l'année {$this->year} :");

        foreach ($this->balances as $balance) {
            $days = number_format($balance['available'], 2, ',', ' ');
            $mail->line("• {$balance['name']} : **{$days} jour(s)**");
        }

        $mail->action('Consulter mes soldes', url('/conges/soldes'))
            ->salutation("Application de gestion du personnel de l'OMIT");

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        $lines = array_map(
            fn (array $b): string => "{$b['name']} : {$b['available']} j",
            $this->balances,
        );

        return [
            'request_id' => null,
            'reference' => null,
            'title' => "Solde de congés {$this->year}",
            'message' => 'Solde disponible : '.implode(' | ', $lines).'.',
            'link' => '/conges/soldes',
        ];
    }
}

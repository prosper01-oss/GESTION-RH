<?php

namespace App\Notifications\Channels;

use App\Contracts\SmsGateway;
use App\Models\User;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private SmsGateway $gateway) {}

    /**
     * Envoie le texte de toSms() au téléphone professionnel de l'agent, s'il est renseigné.
     */
    public function send(User $notifiable, Notification $notification): void
    {
        $phone = $notifiable->employee?->phone_pro;

        if ($phone === null || $phone === '' || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->gateway->send($phone, $notification->toSms($notifiable));
    }
}

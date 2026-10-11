<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Passerelle par défaut : le SMS est écrit dans le journal, rien n'est envoyé.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $phoneNumber, string $message): void
    {
        Log::info('SMS (non envoyé, passerelle de journalisation)', ['to' => $phoneNumber, 'message' => $message]);
    }
}

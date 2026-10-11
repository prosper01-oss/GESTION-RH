<?php

namespace App\Contracts;

/**
 * Passerelle d'envoi de SMS. Le fournisseur reste à choisir (section 15 du cahier des charges) :
 * une nouvelle implémentation se branche dans config/omit.php sans toucher aux notifications.
 */
interface SmsGateway
{
    /**
     * Envoie un SMS. Lève une exception en cas d'échec, pour que la file d'attente réessaie.
     */
    public function send(string $phoneNumber, string $message): void;
}

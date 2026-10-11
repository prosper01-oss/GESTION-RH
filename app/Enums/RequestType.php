<?php

namespace App\Enums;

enum RequestType: string
{
    case Conge = 'conge';
    case Permission = 'permission';
    case Sortie = 'sortie';

    /**
     * Préfixe de la référence, par exemple OMIT-CG-2026-00125.
     */
    public function referencePrefix(): string
    {
        return match ($this) {
            self::Conge => 'CG',
            self::Permission => 'PM',
            self::Sortie => 'BS',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Conge => 'Congé',
            self::Permission => 'Permission',
            self::Sortie => 'Billet de sortie',
        };
    }
}

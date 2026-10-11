<?php

namespace App\Enums;

enum RoleName: string
{
    case Personnel = 'personnel';
    case RessourcesHumaines = 'rh';
    case MedecinChef = 'medecin_chef';
    case Administrateur = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Personnel => 'Personnel',
            self::RessourcesHumaines => 'Ressources Humaines',
            self::MedecinChef => 'Médecin-Chef',
            self::Administrateur => 'Administrateur informatique',
        };
    }

    /**
     * Permissions accordées au rôle (matrice de la section 2.2 du cahier des charges).
     *
     * @return list<PermissionName>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Personnel => [],
            self::RessourcesHumaines => [
                PermissionName::ViewEmployees,
                PermissionName::ManageEmployees,
                PermissionName::ManageDepartments,
            ],
            self::MedecinChef => [
                PermissionName::ViewEmployees,
            ],
            self::Administrateur => [
                PermissionName::ManageUsers,
                PermissionName::ViewAuditLogs,
            ],
        };
    }
}

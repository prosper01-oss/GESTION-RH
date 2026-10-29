<?php

namespace App\Enums;

enum RoleName: string
{
    case Personnel = 'personnel';
    case RessourcesHumaines = 'rh';
    case MedecinChef = 'medecin_chef';
    case Administrateur = 'admin';
    case Pharmacien = 'pharmacien';
    case Caissier = 'caissier';
    case Medecin = 'medecin';
    case Dentiste = 'dentiste';

    public function label(): string
    {
        return match ($this) {
            self::Personnel => 'Personnel',
            self::RessourcesHumaines => 'Ressources Humaines',
            self::MedecinChef => 'Médecin-Chef',
            self::Administrateur => 'Administrateur informatique',
            self::Pharmacien => 'Pharmacien',
            self::Caissier => 'Caissier',
            self::Medecin => 'Médecin',
            self::Dentiste => 'Dentiste',
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
                PermissionName::ManageLeaveSettings,
                PermissionName::ViewLeaveBalances,
                PermissionName::AdjustLeaveBalances,
                PermissionName::ReviewRequests,
                PermissionName::ViewReports,
            ],
            self::MedecinChef => [
                PermissionName::ViewEmployees,
                PermissionName::ViewLeaveBalances,
                PermissionName::DecideRequests,
                PermissionName::ViewMedicalAttachments,
                PermissionName::ViewReports,
            ],
            self::Administrateur => [
                PermissionName::ManageUsers,
                PermissionName::ViewAuditLogs,
                PermissionName::ManageLeaveSettings,
                PermissionName::ManageBackups,
                PermissionName::PharmaDashboard,
                PermissionName::PharmaGestion,
                PermissionName::PharmaArticles,
                PermissionName::PharmaMouvements,
                PermissionName::PharmaInventaire,
                PermissionName::PharmaReappro,
                PermissionName::PharmaCmm,
                PermissionName::PharmaCaisse,
                PermissionName::PharmaHistorique,
                PermissionName::PharmaConsultations,
            ],
            self::Pharmacien => [
                PermissionName::PharmaDashboard,
                PermissionName::PharmaArticles,
                PermissionName::PharmaEntrees,
                PermissionName::PharmaSorties,
                PermissionName::PharmaMouvements,
                PermissionName::PharmaInventaire,
                PermissionName::PharmaReappro,
                PermissionName::PharmaCmm,
                PermissionName::PharmaCaisse,
            ],
            self::Caissier => [
                PermissionName::PharmaDashboard,
                PermissionName::PharmaCaisse,
                PermissionName::PharmaHistorique,
            ],
            self::Medecin, self::Dentiste => [
                PermissionName::PharmaConsultations,
            ],
        };
    }

    /**
     * Droits transmis à un suppléant pendant une suppléance (section 2.2).
     *
     * @return list<PermissionName>
     */
    public function delegablePermissions(): array
    {
        return match ($this) {
            self::RessourcesHumaines => [PermissionName::ReviewRequests],
            self::MedecinChef => [PermissionName::DecideRequests, PermissionName::ViewMedicalAttachments],
            default => [],
        };
    }
}

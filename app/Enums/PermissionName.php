<?php

namespace App\Enums;

enum PermissionName: string
{
    case ViewEmployees = 'employees.view';
    case ManageEmployees = 'employees.manage';
    case ManageDepartments = 'departments.manage';
    case ManageUsers = 'users.manage';
    case ViewAuditLogs = 'audit_logs.view';
    case ManageLeaveSettings = 'leave_settings.manage';
    case ViewLeaveBalances = 'leave_balances.view';
    case AdjustLeaveBalances = 'leave_balances.adjust';
    case ReviewRequests = 'requests.review';
    case DecideRequests = 'requests.decide';
    case ViewMedicalAttachments = 'attachments.view_medical';

    public function description(): string
    {
        return match ($this) {
            self::ViewEmployees => 'Consulter tous les dossiers du personnel',
            self::ManageEmployees => 'Créer, modifier et désactiver les fiches employé',
            self::ManageDepartments => 'Créer et modifier les services',
            self::ManageUsers => 'Gérer les comptes et les rôles',
            self::ViewAuditLogs => "Consulter le journal d'audit",
            self::ManageLeaveSettings => 'Paramétrer les types de congés et les jours fériés',
            self::ViewLeaveBalances => 'Consulter les soldes de congés de tous les agents',
            self::AdjustLeaveBalances => 'Corriger un solde de congés, avec justification',
            self::ReviewRequests => 'Contrôler les demandes (étape RH) et les finaliser',
            self::DecideRequests => 'Décider des demandes transmises par les RH (étape Médecin-Chef)',
            self::ViewMedicalAttachments => 'Consulter les justificatifs médicaux',
        };
    }
}

<?php

namespace App\Enums;

enum PermissionName: string
{
    case ViewEmployees = 'employees.view';
    case ManageEmployees = 'employees.manage';
    case ManageDepartments = 'departments.manage';
    case ManageUsers = 'users.manage';
    case ViewAuditLogs = 'audit_logs.view';

    public function description(): string
    {
        return match ($this) {
            self::ViewEmployees => 'Consulter tous les dossiers du personnel',
            self::ManageEmployees => 'Créer, modifier et désactiver les fiches employé',
            self::ManageDepartments => 'Créer et modifier les services',
            self::ManageUsers => 'Gérer les comptes et les rôles',
            self::ViewAuditLogs => "Consulter le journal d'audit",
        };
    }
}

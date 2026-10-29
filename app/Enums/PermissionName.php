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
    case ViewReports = 'reports.view';
    case ManageBackups = 'backups.manage';
    case PharmaDashboard = 'pharma.dashboard';
    case PharmaGestion = 'pharma.gestion';
    case PharmaArticles = 'pharma.articles';
    case PharmaEntrees = 'pharma.entrees';
    case PharmaSorties = 'pharma.sorties';
    case PharmaMouvements = 'pharma.mouvements';
    case PharmaInventaire = 'pharma.inventaire';
    case PharmaReappro = 'pharma.reappro';
    case PharmaCmm = 'pharma.cmm';
    case PharmaCaisse = 'pharma.caisse';
    case PharmaHistorique = 'pharma.historique';
    case PharmaConsultations = 'pharma.consultations';

    public function description(): string
    {
        return match ($this) {
            self::ViewEmployees => 'Consulter tous les dossiers du personnel',
            self::ManageEmployees => 'Créer, modifier et désactiver les fiches employé',
            self::ManageDepartments => 'Créer et modifier les services',
            self::ManageUsers => 'Gérer les comptes et les rôles',
            self::ViewAuditLogs => "Consulter le journal d'audit",
            self::ManageLeaveSettings => 'Paramétrer les types de congés, les catégories de permission et les jours fériés',
            self::ViewLeaveBalances => 'Consulter les soldes de congés de tous les agents',
            self::AdjustLeaveBalances => 'Corriger un solde de congés, avec justification',
            self::ReviewRequests => 'Contrôler les demandes (étape RH) et les finaliser',
            self::DecideRequests => 'Décider des demandes transmises par les RH (étape Médecin-Chef)',
            self::ViewMedicalAttachments => 'Consulter les justificatifs médicaux',
            self::ViewReports => 'Consulter le tableau de bord RH et exporter les rapports',
            self::ManageBackups => "Consulter l'état des sauvegardes et en lancer une",
            self::PharmaDashboard => 'Pharmacie : consulter le tableau de bord',
            self::PharmaGestion => 'Pharmacie : gérer les référentiels (analyses, échographies, catégories, services, personnel, kits)',
            self::PharmaArticles => 'Pharmacie : gérer les articles et les fournisseurs',
            self::PharmaEntrees => 'Pharmacie : enregistrer et valider les entrées de stock',
            self::PharmaSorties => 'Pharmacie : enregistrer les sorties de stock (pertes, casse, ajustements)',
            self::PharmaMouvements => 'Pharmacie : consulter le journal des mouvements de stock',
            self::PharmaInventaire => 'Pharmacie : réaliser les inventaires',
            self::PharmaReappro => 'Pharmacie : préparer le réapprovisionnement',
            self::PharmaCmm => 'Pharmacie : consulter la consommation moyenne mensuelle',
            self::PharmaCaisse => 'Pharmacie : tenir la caisse (sessions, paniers, ventes)',
            self::PharmaHistorique => "Pharmacie : consulter l'historique des ventes",
            self::PharmaConsultations => 'Pharmacie : recevoir les patients adressés par la caisse (médecin, dentiste)',
        };
    }

    /**
     * Permissions des modules Pharmacie, attribuables individuellement à un compte.
     *
     * @return list<self>
     */
    public static function pharma(): array
    {
        return array_values(array_filter(self::cases(), fn (self $permission): bool => str_starts_with($permission->value, 'pharma.')));
    }
}

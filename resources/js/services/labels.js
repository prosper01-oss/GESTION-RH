export const roleLabels = {
    personnel: 'Personnel',
    rh: 'Ressources Humaines',
    medecin_chef: 'Médecin-Chef',
    admin: 'Administrateur',
    pharmacien: 'Pharmacien',
    caissier: 'Caissier',
    medecin: 'Médecin',
    dentiste: 'Dentiste',
};

export const sexeLabels = {
    masculin: 'Masculin',
    feminin: 'Féminin',
};

export const situationFamilialeLabels = {
    celibataire: 'Célibataire',
    marie: 'Marié(e)',
    divorce: 'Divorcé(e)',
    veuf: 'Veuf / Veuve',
};

export const contractTypeLabels = {
    cdi: 'CDI',
    cdd: 'CDD',
    stage: 'Stage',
    autre: 'Autre',
};

export const employeeStatusLabels = {
    actif: 'Actif',
    inactif: 'Inactif',
};

export function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('fr-FR') : '—';
}

export const accrualRuleLabels = {
    monthly: 'Mensuelle (droit annuel / 12)',
    annual: 'Annuelle (au 1er janvier)',
    none: 'Aucune acquisition',
};

export const countingModeLabels = {
    calendar: 'Jours calendaires',
    calendar_excluding_holidays: 'Jours calendaires hors jours fériés',
    working: 'Jours ouvrés',
};

export function formatDays(value) {
    const number = Number(value ?? 0);
    const text = number.toLocaleString('fr-FR', { maximumFractionDigits: 2 });

    return `${text} j`;
}

export function formatPeriod(leave) {
    if (!leave) {
        return '—';
    }
    const start = `${formatDate(leave.start_date)}${leave.starts_afternoon ? ' (après-midi)' : ''}`;
    const end = `${formatDate(leave.end_date)}${leave.ends_morning ? ' (matin)' : ''}`;

    return leave.start_date === leave.end_date && !leave.starts_afternoon && !leave.ends_morning ? start : `du ${start} au ${end}`;
}

export const requestTypeLabels = {
    conge: 'Congé',
    permission: 'Permission',
    sortie: 'Billet de sortie',
};

export function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}

export function formatHours(value) {
    return `${Number(value ?? 0).toLocaleString('fr-FR', { maximumFractionDigits: 2 })} h`;
}

/**
 * Résumé d'une demande, quel que soit son type : intitulé, période et quantité.
 */
export function requestSummary(request) {
    if (request.leave) {
        return { title: request.leave.leave_type.name, period: formatPeriod(request.leave), quantity: formatDays(request.leave.requested_days) };
    }
    if (request.permission) {
        return {
            title: request.permission.category_label ?? request.permission.permission_type.name,
            period: `${formatDateTime(request.permission.start_at)} → ${formatDateTime(request.permission.end_at)}`,
            quantity: formatHours(request.permission.duration_hours),
        };
    }
    if (request.exit_pass) {
        return {
            title: `Sortie : ${request.exit_pass.destination}`,
            period: `${formatDateTime(request.exit_pass.departure_at)} → ${formatDateTime(request.exit_pass.expected_return_at)}`,
            quantity: '—',
        };
    }

    return { title: requestTypeLabels[request.request_type], period: '—', quantity: '—' };
}

/**
 * Chemins des formulaires de création et de modification par type de demande.
 */
export const requestFormPaths = {
    conge: '/demandes/nouvelle',
    permission: '/demandes/permission/nouvelle',
    sortie: '/demandes/sortie/nouvelle',
};

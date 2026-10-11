export const roleLabels = {
    personnel: 'Personnel',
    rh: 'Ressources Humaines',
    medecin_chef: 'Médecin-Chef',
    admin: 'Administrateur',
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

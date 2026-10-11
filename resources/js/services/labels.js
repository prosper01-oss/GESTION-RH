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

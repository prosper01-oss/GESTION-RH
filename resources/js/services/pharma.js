/**
 * Montant en Ariary avec séparateurs de milliers (« 12 500 »), jusqu'à 2 décimales ou exactement
 * `decimales` si précisé. Chaîne vide si la valeur n'est pas un nombre.
 */
export function montant(valeur, decimales) {
    if (valeur === null || valeur === undefined || valeur === '') {
        return '';
    }
    const n = Number(String(valeur).replace(/\s/g, ''));

    if (!Number.isFinite(n)) {
        return '';
    }

    return n.toLocaleString('fr-FR', decimales === undefined ? { maximumFractionDigits: 2 } : { minimumFractionDigits: decimales, maximumFractionDigits: decimales });
}

/**
 * Valeur saisie dans un champ de prix : ne garde que les chiffres.
 */
export function chiffresSeulement(texte) {
    return String(texte ?? '').replace(/\D/g, '');
}

export function formatDate(valeur) {
    if (!valeur) {
        return '—';
    }
    // Une date seule (AAAA-MM-JJ) est affichée telle quelle, sans décalage de fuseau.
    const jour = /^(\d{4})-(\d{2})-(\d{2})$/.exec(valeur);

    return jour ? `${jour[3]}/${jour[2]}/${jour[1]}` : new Date(valeur).toLocaleDateString('fr-FR');
}

export function formatDateHeure(valeur) {
    return valeur ? new Date(valeur).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—';
}

export const motifSortieLabels = {
    ajustement: 'Ajustement',
    perte: 'Perte',
    casse: 'Casse',
    perime: 'Périmé',
};

export const mouvementLabels = {
    entree: 'Entrée',
    entree_annulation: 'Entrée annulée',
    sortie: 'Sortie / perte',
    sortie_restauration: 'Sortie restituée',
    panier: 'Réservation caisse',
    panier_retrait: 'Retrait panier',
    inventaire: 'Inventaire',
    inventaire_annulation: 'Inventaire annulé',
};

export const inventaireStatutLabels = {
    brouillon: 'Brouillon',
    valide: 'Validé',
    applique: 'Appliqué',
    annule: 'Annulé',
};

export const peremptionLabels = {
    perime: 'Périmé',
    urgent: '≤ 30 jours',
    proche: '≤ 90 jours',
    ok: 'OK',
};

/** Modules de la Pharmacie, attribuables individuellement à un compte (en plus de ceux de ses rôles). */
export const moduleLabels = {
    'pharma.dashboard': 'Tableau de bord',
    'pharma.caisse': 'Caisse',
    'pharma.historique': 'Historique des ventes',
    'pharma.consultations': 'Mes patients (médecin, dentiste)',
    'pharma.articles': 'Articles et fournisseurs',
    'pharma.entrees': 'Entrées de stock',
    'pharma.sorties': 'Sorties de stock',
    'pharma.mouvements': 'Mouvements',
    'pharma.inventaire': 'Inventaire',
    'pharma.reappro': 'Réapprovisionnement',
    'pharma.cmm': 'CMM',
    'pharma.gestion': 'Gestion du catalogue',
};

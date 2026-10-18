# Guide des Ressources Humaines

Ce guide couvre le travail des RH dans l'application : dossiers du personnel, soldes de congés, contrôle et finalisation des demandes, rapports. Pour vos propres demandes de congé, suivez le [guide de l'agent](AGENT.md).

## Mise en service : reprise des données existantes

À faire une seule fois, avant d'ouvrir l'application aux agents.

1. **Paramètres.** Vérifiez et corrigez :
   - **Types de congés** : droit annuel, mode de décompte, report maximal, délai de dépôt minimal (15 jours pour le congé annuel, règle R8) et délai du justificatif (48 h pour le congé de maladie, R6) ;
   - **Catégories de permission** ;
   - **Jours fériés** : ajoutez chaque année le lundi de Pâques, l'Ascension et le lundi de Pentecôte ;
   - **Services** : désignez le **responsable** de chaque service (bouton **Modifier**). C'est lui qui valide les billets de sortie de son service (règle R7) ; sans responsable, ils vont au Médecin-Chef.
2. **Personnel**, puis **Importer**, puis **Fiches du personnel**. Téléchargez le modèle CSV, remplissez-le dans Excel et enregistrez-le au format CSV. Cliquez sur **Vérifier le fichier**, corrigez les lignes signalées, puis cliquez sur **Importer**.
3. **Importer**, puis **Soldes de congés** : reprenez le solde de chaque agent à la date de démarrage. Chaque ligne devient une correction visible dans l'historique du solde.
4. Faites contrôler les soldes importés par une seconde personne (section 12 du cahier des charges).
5. Transmettez à l'administrateur informatique la liste des matricules pour lesquels créer un compte.

## Dossiers du personnel

Le menu **Personnel** permet de rechercher par nom ou matricule et de filtrer par service et par statut. Depuis une fiche, vous pouvez la modifier, ajouter une photo, la désactiver ou consulter et corriger les soldes.

Une fiche n'est jamais supprimée : on la **désactive**. Le compte de connexion de l'agent est alors désactivé lui aussi.

**Corriger un solde** : la correction exige un motif, et elle reste visible dans l'historique. Mettez un nombre négatif pour retirer des jours.

## Traiter les demandes

Le menu **À traiter** a trois onglets pour les congés et les permissions. Les billets de sortie n'y passent pas : ils sont validés par le chef de service (voir plus bas).

- **À contrôler** : demandes envoyées par les agents, ou renvoyées par le Médecin-Chef. Ouvrez la demande, vérifiez les dates, le justificatif et le solde (affiché dans la demande), puis :
  - **Transmettre au Médecin-Chef** ;
  - **Renvoyer à l'agent pour correction** (commentaire obligatoire) ;
  - **Refuser** (motif obligatoire, définitif).
- **À finaliser** : demandes approuvées par le Médecin-Chef. Téléchargez le PDF, faites signer, enregistrez, puis cliquez sur **Finaliser**.
- **Toutes les demandes** : vue d'ensemble.

Une demande marquée **Urgence** a été déposée moins de 15 jours à l'avance : vérifiez le motif indiqué par l'agent.

Pour un congé de maladie, le justificatif peut arriver après l'envoi (sous 48 h après le premier jour). La demande affiche l'échéance ; passé ce délai, vous recevez l'alerte « Justificatif manquant » et décidez de la suite (relance, refus).

Vous ne voyez jamais vos propres demandes dans ces files, et vous ne pouvez pas les valider.

Vous ne voyez pas non plus les **justificatifs médicaux** : seuls l'agent et le Médecin-Chef y ont accès.

### Après la finalisation

- **Congé :** il se clôture seul après sa date de fin. En cas de retour anticipé, utilisez **Interrompre le congé** : seuls les jours réellement pris sont décomptés.
- **Annulation d'un congé approuvé :** possible jusqu'à la veille de son début. Les jours sont rendus à l'agent.
### Billets de sortie

Le chef de service valide la sortie ; vous êtes **informé** de chaque sortie autorisée (notification « Sortie autorisée »). Dans le menu **Sorties**, enregistrez le **départ** puis le **retour** de l'agent, ce que le chef de service peut aussi faire depuis la demande. Les retours en retard sont signalés en rouge. Vous seul pouvez annuler une sortie autorisée, avant le départ (règle R5).

## Rapports

Le menu **Tableau de bord RH** affiche les indicateurs du jour : personnel actif, absents, demandes en attente, sorties en cours.

Le rapport des absences se règle par période (raccourcis « Ce mois-ci », « Cette année »…), par service et par type. Il s'exporte en **PDF** ou en **Excel**. L'effectif par service s'exporte aussi. Chaque export est enregistré dans le journal d'audit.

## Suppléance

En cas d'absence, désignez un suppléant RH dans le menu **Suppléances**. Pendant la période choisie, il contrôle les demandes à votre place. Il ne peut pas traiter vos propres demandes.

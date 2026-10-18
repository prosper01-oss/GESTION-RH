# Guide de l'administrateur

Ce guide couvre la gestion des comptes et les écrans d'administration. L'installation du serveur et l'exploitation sont décrites dans [INSTALLATION.md](../INSTALLATION.md) et [EXPLOITATION.md](../EXPLOITATION.md).

L'administrateur gère la partie technique, mais **n'a pas accès aux données du personnel** : ni l'annuaire, ni les demandes, ni les soldes (section 2.2 du cahier des charges). Ce sont les RH qui créent les fiches.

## Créer les comptes

1. Menu **Comptes**, puis **Nouveau compte**.
2. Saisissez :
   - le nom affiché et l'adresse email ;
   - le **matricule** de l'employé, transmis par les RH, pour rattacher le compte à sa fiche ;
   - les rôles.
3. Un mot de passe provisoire s'affiche **une seule fois** : transmettez-le à la personne. Elle devra le changer à sa première connexion.

| Rôle | À attribuer à |
|---|---|
| Personnel | Tout agent, y compris les RH, le Médecin-Chef et vous-même si vous êtes employé de l'OMIT |
| Ressources Humaines | Les agents du service RH (en plus de « Personnel ») |
| Médecin-Chef | Le Médecin-Chef (en plus de « Personnel ») |
| Administrateur | L'administrateur informatique |

Le responsable d'un service n'a pas de rôle particulier : un simple compte « Personnel » suffit. Ce sont les RH qui le désignent dans l'écran **Services**, et il voit alors l'onglet « Sorties à valider ».

Un compte sans matricule ne peut pas déposer de demandes.

## Opérations sur les comptes

- **Mot de passe oublié** : bouton **Réinitialiser**. Un nouveau mot de passe provisoire s'affiche.
- **Rôles** : bouton **Rôles**. Vous ne pouvez pas retirer votre propre rôle d'administrateur.
- **Départ d'un agent** : ce sont les RH qui désactivent sa fiche, ce qui désactive son compte.

## Paramètres

Les menus **Types de congés**, **Catégories de permission** et **Jours fériés** sont accessibles à l'administrateur comme aux RH. Ne modifiez les règles qu'à la demande écrite des RH : chaque changement crée une nouvelle version datée et reste tracé.

## Journal d'audit

Le menu **Journal d'audit** retrace les actions sensibles : connexions et échecs de connexion, création de comptes et de fiches, corrections de soldes, exports, consultation de justificatifs médicaux, sauvegardes.

Il se filtre par action et par période. Pour les fiches du personnel, seuls les champs modifiés sont visibles, pas leurs valeurs.

## Sauvegardes

Le menu **Sauvegardes** affiche les archives des 30 derniers jours et la dernière erreur éventuelle. Une alerte apparaît si aucune sauvegarde n'a eu lieu depuis 36 heures. **Sauvegarder maintenant** lance une sauvegarde immédiate, par exemple avant une intervention.

La restauration se fait sur le serveur, en ligne de commande : voir [EXPLOITATION.md](../EXPLOITATION.md).

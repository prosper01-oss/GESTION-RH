# Cahier de recette

Ce cahier vérifie, dans le navigateur, que l'application respecte les critères de recette du cahier des charges (section 13 de la v2.1, REC-01 à REC-19). Il se déroule avec quatre personnes, ou une personne qui change de compte. Comptez une demi-journée.

Pour chaque étape, cochez **OK** si le résultat obtenu est celui attendu. Sinon, notez l'écart dans la colonne « Remarque » et reportez-le au tableau des anomalies en fin de document.

Utilisez deux navigateurs différents, ou une fenêtre de navigation privée, pour être connecté avec deux comptes en même temps.

## 0. Préparation

| # | Qui | Action | Résultat attendu | OK | Remarque |
|---|---|---|---|---|---|
| 0.1 | Admin | Se connecter avec le compte administrateur et son mot de passe provisoire | L'application impose de choisir un nouveau mot de passe, puis affiche le tableau de bord | ☐ | |
| 0.2 | Admin | **Comptes**, puis **Nouveau compte** : « RH Test », rôles Personnel et Ressources Humaines, sans matricule | Un mot de passe provisoire s'affiche une seule fois | ☐ | |
| 0.3 | RH | Se connecter, changer le mot de passe. **Services** : créer « Médecine du travail » (MED) et « Laboratoire » (LAB) | Les deux services apparaissent avec un effectif de 0 | ☐ | |
| 0.4 | RH | **Personnel**, puis **Nouvel employé** : créer « Agent Test » (matricule T-001, MED), « Médecin Test » (T-002, MED) et « RH Fiche » (T-003, MED) | Les trois fiches apparaissent dans l'annuaire | ☐ | |
| 0.5 | Admin | **Comptes**, puis **Nouveau compte** : « Agent Test » (matricule T-001, rôle Personnel) et « Médecin Test » (matricule T-002, rôles Personnel et Médecin-Chef) | Comptes créés et rattachés à leur fiche ; chacun change son mot de passe à la première connexion | ☐ | |
| 0.6 | RH | Fiche d'Agent Test, **Corriger un solde** : congé annuel, année en cours, **10** jours, motif « Solde de recette » | Le solde disponible affiche 10 j ; l'historique montre la correction avec le motif | ☐ | |

## 1. Accès et sécurité

| # | Critère | Qui | Action | Résultat attendu | OK | Remarque |
|---|---|---|---|---|---|---|
| 1.1 | EF-01 | — | Se connecter 6 fois de suite avec un mauvais mot de passe | À la 6ᵉ tentative : « Trop de tentatives. Réessayez dans une minute. » | ☐ | |
| 1.2 | REC-01 | Agent | Ouvrir l'adresse `/personnel` dans la barre d'adresse | Le menu « Personnel » est absent ; l'adresse ramène au tableau de bord | ☐ | |
| 1.3 | 2.2 | Admin | Vérifier le menu | Pas de menu « Personnel » ni « À traiter » : l'administrateur n'accède pas aux données RH | ☐ | |
| 1.4 | EF-03 | Agent | Rester 30 minutes sans rien faire, puis cliquer | Retour à l'écran de connexion | ☐ | |

## 2. Demande de congé : circuit complet

| # | Critère | Qui | Action | Résultat attendu | OK | Remarque |
|---|---|---|---|---|---|---|
| 2.1 | EF-22 | Agent | **Mes demandes**, puis **+ Congé** : congé annuel, du lundi au vendredi dans deux semaines | « Jours décomptés : 5 j » s'affiche avant l'enregistrement | ☐ | |
| 2.2 | — | Agent | **Enregistrer le brouillon**, puis **Envoyer aux RH** | Statut « Soumise » ; référence `OMIT-CG-AAAA-00001` | ☐ | |
| 2.3 | EF-60 | RH | Regarder **Notifications** en haut de l'écran | Alerte « Demande à contrôler » ; un clic ouvre la demande | ☐ | |
| 2.4 | REC-02 | Médecin | **À traiter**, onglet **À décider** | La demande n'y figure pas encore : l'étape RH ne peut pas être sautée | ☐ | |
| 2.5 | — | RH | Ouvrir la demande : le solde de l'agent est affiché. **Transmettre au Médecin-Chef** | Statut « Transmise au Médecin-Chef » | ☐ | |
| 2.6 | REC-02 | Médecin | **À décider**, ouvrir la demande, **Approuver** | Statut « Approuvée » | ☐ | |
| 2.7 | 4.3 | Agent | Tableau de bord, **Mes soldes** | Disponible : 5 j ; Réservés : 5 j | ☐ | |
| 2.8 | EF-54 | RH | **À finaliser**, ouvrir, **Télécharger l'autorisation (PDF)**, puis **Finaliser** | Le PDF porte l'en-tête OMIT, la référence, les signatures et « Approuvé par Médecin Test » ; statut « Finalisée » | ☐ | |
| 2.9 | EF-28 | Agent | Ouvrir la demande, télécharger le PDF | Même document | ☐ | |
| 2.10 | REC-02 | Agent | Ouvrir l'historique de la demande | Chaque étape montre qui, quand, et le commentaire éventuel | ☐ | |

## 3. Refus, correction, renvoi

| # | Critère | Qui | Action | Résultat attendu | OK | Remarque |
|---|---|---|---|---|---|---|
| 3.1 | REC-03 | Agent → RH → Médecin | Nouvelle demande de 1 jour, envoyée et transmise. Le Médecin clique sur **Refuser** sans commentaire | Le commentaire est exigé ; rien ne change | ☐ | |
| 3.2 | REC-03 | Médecin | Refuser avec le motif « Test de refus » | Statut « Refusée » ; l'historique montre l'auteur, la date et le motif ; l'agent reçoit une notification avec le motif | ☐ | |
| 3.3 | 3.3 | RH | Sur une nouvelle demande soumise : **Renvoyer à l'agent pour correction**, commentaire « Dates à revoir » | Statut « À corriger » | ☐ | |
| 3.4 | 3.3 | Agent | **Modifier** les dates, puis **Envoyer aux RH** | La demande repart en « Soumise » avec la même référence | ☐ | |
| 3.5 | 3.3 | Médecin | Après transmission : **Renvoyer aux RH**, commentaire « Complément » | Statut « Renvoyée aux RH » ; la demande revient dans « À contrôler » | ☐ | |

## 4. Soldes

| # | Critère | Qui | Action | Résultat attendu | OK | Remarque |
|---|---|---|---|---|---|---|
| 4.1 | REC-15 | Agent | Noter le solde disponible, puis envoyer deux demandes à des dates différentes qui, ensemble, le dépassent (chacune restant en dessous) | La 2ᵉ est refusée à l'envoi : « Solde insuffisant… en tenant compte des demandes en cours » | ☐ | |
| 4.2 | EF-23 | Agent | Envoyer une demande dont les dates chevauchent une demande déjà envoyée | Refus : « Cette période chevauche la demande OMIT-CG-… » | ☐ | |
| 4.3 | REC-06 | RH | Sur une demande approuvée qui n'a pas commencé : **Annuler la demande** | Statut « Annulée » ; les jours réservés reviennent dans le solde | ☐ | |
| 4.4 | REC-04 | RH | Comparer les soldes de trois agents réels avec le calcul manuel des RH (après reprise des données) | Soldes identiques | ☐ | |
| 4.5 | REC-17 | RH | Interruption : à tester sur un congé finalisé déjà commencé (créer un congé débutant aujourd'hui) | Seuls les jours réellement pris sont décomptés | ☐ | |

## 5. Règles particulières

| # | Critère | Qui | Action | Résultat attendu | OK | Remarque |
|---|---|---|---|---|---|---|
| 5.1 | REC-07 | Admin + RH | L'administrateur crée le compte « RH Fiche » (matricule T-003, rôles Personnel et Ressources Humaines). Ce compte fait une demande et l'envoie | La demande n'apparaît pas dans sa propre file « À contrôler » ; elle apparaît dans celle de l'autre RH | ☐ | |
| 5.2 | REC-08 | Médecin | **Suppléances** : désigner « RH Test » comme suppléant pour aujourd'hui | « RH Test » voit l'onglet « À décider » ; sa décision affiche « en suppléance de Médecin Test » | ☐ | |
| 5.3 | 2.2 | Médecin | **Mettre fin** à la suppléance | « RH Test » ne voit plus l'onglet « À décider » après rechargement | ☐ | |
| 5.4 | REC-09 | Agent → RH → Médecin | Congé de maladie avec un justificatif PDF, envoyé | Les RH voient « (accès restreint) » à la place du fichier ; le Médecin-Chef peut l'ouvrir ; le journal d'audit enregistre la consultation | ☐ | |
| 5.5 | REC-16 | Agent | Retirer une demande soumise (**Annuler la demande**) ; supprimer un brouillon | Les deux fonctionnent | ☐ | |

## 6. Permissions et billets de sortie

| # | Critère | Qui | Action | Résultat attendu | OK | Remarque |
|---|---|---|---|---|---|---|
| 6.1 | EF-31 | Agent | **+ Permission** « Convenance personnelle » de 09 h 00 à 13 h 00 | Durée affichée : 4 h. Au-delà de 8 h, l'envoi est refusé (durée maximale) | ☐ | |
| 6.2 | EF-40 | Agent | **+ Billet de sortie** dans une heure, retour prévu une heure après, destination et motif | Après envoi : référence `OMIT-BS-…` | ☐ | |
| 6.3 | EF-41 | RH | Après approbation et finalisation : **Sorties**, puis **Départ** | Le billet passe dans « En cours » | ☐ | |
| 6.4 | EF-42 | RH | Attendre que l'heure de retour prévue soit dépassée | La ligne est signalée en rouge : « Retour prévu dépassé » | ☐ | |
| 6.5 | EF-41 | RH | **Retour** | Statut « Terminée » ; l'heure de retour réelle figure dans la demande | ☐ | |
| 6.6 | REC-10 | Agent | Télécharger le billet de sortie (PDF) | En-tête OMIT, référence `OMIT-BS-…`, zones de signature | ☐ | |

## 7. Rapports, notifications, administration

| # | Critère | Qui | Action | Résultat attendu | OK | Remarque |
|---|---|---|---|---|---|---|
| 7.1 | EF-70 | RH | **Tableau de bord RH** | Les chiffres correspondent aux tests faits ci-dessus | ☐ | |
| 7.2 | REC-11 | RH | Rapport des absences « Ce mois-ci » : **Exporter en PDF**, puis **Exporter en Excel** | Les deux fichiers s'ouvrent et contiennent les absences accordées | ☐ | |
| 7.3 | REC-11 | Agent | Ouvrir l'adresse `/rapports` | Retour au tableau de bord : accès refusé | ☐ | |
| 7.4 | EF-63 | Agent | **Préférences de notification** : décocher les emails | La préférence est conservée après rechargement | ☐ | |
| 7.5 | EF-82 | Admin | **Journal d'audit**, filtrer sur `auth.login` | Les connexions de la recette apparaissent | ☐ | |
| 7.6 | 2.2 | Admin | Après une modification de fiche par les RH (par exemple la fonction d'Agent Test), filtrer le journal sur `employee.updated` | Seuls les noms des champs modifiés sont visibles, pas leurs valeurs | ☐ | |
| 7.7 | — | Admin | **Sauvegardes**, puis **Sauvegarder maintenant** | Une archive datée apparaît dans la liste | ☐ | |

## 8. Points à faire sur le serveur de production

Ces critères ne se vérifient qu'une fois l'application installée sur le serveur de l'OMIT (voir [INSTALLATION.md](INSTALLATION.md)) :

| # | Critère | Vérification | OK | Remarque |
|---|---|---|---|---|
| 8.1 | REC-12 | Couper Internet, faire envoyer une demande, rétablir Internet : l'email part au retour de la connexion | ☐ | |
| 8.2 | REC-13 | Restaurer une sauvegarde sur un serveur de test (voir [EXPLOITATION.md](EXPLOITATION.md)) | ☐ | |
| 8.3 | REC-14 | Internet coupé, réseau local actif : connexion, demande et validation fonctionnent | ☐ | |
| 8.4 | REC-05 | Deux personnes approuvent deux demandes d'un même agent au même moment, avec un solde juste suffisant pour une seule : une approbation est refusée. Ce cas est aussi couvert par les tests automatiques | ☐ | |
| 8.5 | 6 | L'adresse en `http://` redirige vers `https://` | ☐ | |

## Anomalies relevées

| # | Étape | Description | Gravité (bloquante / majeure / mineure) | Corrigée le |
|---|---|---|---|---|
| | | | | |
| | | | | |
| | | | | |

## Procès-verbal de recette

L'application de gestion du personnel de l'OMIT, version du ……………………, a été vérifiée selon le présent cahier.

☐ **Recette prononcée sans réserve.**
☐ **Recette prononcée avec réserves**, levées au plus tard le …………………… (anomalies n° ……………………).
☐ **Recette refusée.**

| Pour l'OMIT | Fonction | Date | Signature |
|---|---|---|---|
| | Direction | | |
| | Ressources Humaines | | |
| | Médecin-Chef | | |
| | Administrateur informatique | | |

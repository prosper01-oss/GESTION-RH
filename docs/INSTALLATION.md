# Installation en production

Ce guide installe l'application de gestion du personnel de l'OMIT sur le serveur local de l'OMIT. Il s'adresse à l'administrateur informatique. Comptez une demi-journée.

Les exemples utilisent `/var/www/omit-gestion-personnel` comme dossier et `rh.omit.local` comme adresse. Remplacez-les par vos valeurs.

## 1. Prérequis

| Élément | Version | Remarque |
|---|---|---|
| Ubuntu Server | 24.04 LTS | ou Debian 12 |
| PHP | 8.4 (php-fpm) | extensions : mysql, mbstring, xml, zip, gd, intl, curl, bcmath |
| MySQL | 8.0 | sur le même serveur, inaccessible depuis le réseau |
| Apache | 2.4 | modules rewrite, ssl, headers, proxy_fcgi |
| Composer | 2.x | |
| Node.js | 22 | uniquement pour compiler l'interface |
| Onduleur | — | recommandé (section 6 du cahier des charges) |

```bash
sudo apt install php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-zip \
  php8.4-gd php8.4-intl php8.4-curl php8.4-bcmath mysql-server apache2 unzip
```

## 2. Récupérer le code

```bash
sudo git clone <dépôt> /var/www/omit-gestion-personnel
cd /var/www/omit-gestion-personnel
sudo chown -R www-data:www-data storage bootstrap/cache
```

## 3. Configurer l'application

```bash
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
```

Dans `.env`, renseignez au minimum :

| Variable | Valeur en production |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (impératif : sinon les erreurs affichent des données internes) |
| `APP_URL` | `https://rh.omit.local` |
| `APP_TIMEZONE` | `Indian/Antananarivo` |
| `DB_CONNECTION` | `mysql` |
| `DB_DATABASE`, `DB_USERNAME` | `omit_gestion_personnel`, `omit_app` |
| `DB_PASSWORD` | mot de passe long et aléatoire (`openssl rand -base64 24`) |
| `SESSION_LIFETIME` | durée d'inactivité avant déconnexion, en minutes (30 par défaut) |
| `SESSION_SECURE_COOKIE` | `true` |
| `SANCTUM_STATEFUL_DOMAINS` | `rh.omit.local` |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | serveur SMTP de l'OMIT |
| `MAIL_FROM_ADDRESS` | par exemple `rh@omit.mg` |
| `OMIT_BACKUP_COPY_PATH` | dossier sur un second support (disque externe, partage réseau monté) |
| `OMIT_NON_WORKING_DAYS` | jours non ouvrés : `0,6` pour dimanche et samedi |
| `OMIT_REMINDER_DAYS` | délai avant rappel des demandes en attente (3 par défaut) |

Le fichier `.env` contient des secrets : `chmod 640 .env`, propriétaire `www-data`. Il ne doit jamais être copié dans le dépôt Git.

## 4. Créer la base MySQL

Le script lit le nom de la base, l'utilisateur et le mot de passe dans `.env` :

```bash
sudo MYSQL_DEFAULTS_FILE=/etc/mysql/debian.cnf ./scripts/creer-base.sh
php artisan migrate --force
php artisan db:seed --force
```

Le seeder crée les rôles, les permissions et des paramètres de départ : types de congés, catégories de permission et fêtes légales fixes. Ces paramètres sont à faire valider par les RH (section 15 du cahier des charges). Le seeder peut être relancé sans créer de doublons.

**Après l'installation, restreignez les droits MySQL.** Le journal d'audit et l'historique des demandes doivent être en ajout seul (section 8.2) :

```sql
-- Droits complets sur toutes les tables, sauf audit_logs et request_status_history :
SELECT CONCAT('GRANT SELECT, INSERT, UPDATE, DELETE ON omit_gestion_personnel.`', table_name, '` TO ''omit_app''@''localhost'';')
FROM information_schema.tables
WHERE table_schema = 'omit_gestion_personnel' AND table_name NOT IN ('audit_logs', 'request_status_history');
```

Exécutez les instructions produites. Ajoutez `GRANT SELECT, INSERT` sur les deux tables protégées, puis retirez le droit global : `REVOKE ALL ON omit_gestion_personnel.* FROM 'omit_app'@'localhost';`. Avant chaque mise à jour comportant des migrations, rétablissez temporairement les droits de structure.

Lors de la recette, vérifiez que la suppression d'un brouillon de demande fonctionne toujours avec ces droits.

## 5. Compiler l'interface

```bash
npm ci
npm run build
```

Node.js n'est plus nécessaire ensuite.

## 6. Serveur web et HTTPS

Copiez [deploy/apache-omit-rh.conf](../deploy/apache-omit-rh.conf), adaptez le nom et les certificats, puis :

```bash
sudo cp deploy/apache-omit-rh.conf /etc/apache2/sites-available/omit-rh.conf
sudo a2enmod rewrite ssl headers proxy_fcgi setenvif && sudo a2enconf php8.4-fpm
sudo a2dissite 000-default && sudo a2ensite omit-rh && sudo systemctl reload apache2
```

Le HTTPS est exigé même sur le réseau interne (section 6). Il faut aussi :

- un certificat émis par une autorité interne, ou un certificat auto-signé installé sur les postes ;
- un nom DNS interne, `rh.omit.local`, qui pointe vers le serveur.

## 7. Tâches planifiées et file d'attente

```bash
sudo crontab -u www-data deploy/crontab.txt
sudo cp deploy/omit-queue.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now omit-queue
```

Le planificateur exécute :

| Tâche | Quand |
|---|---|
| Acquisition mensuelle des congés (`omit:accrue-leave`) | le 1er de chaque mois, 01 h 00 |
| Clôture annuelle : reports, péremption, droits annuels (`omit:close-leave-year`) | le 1er janvier, 02 h 00 |
| Clôture des congés et permissions terminés (`omit:close-finished-requests`) | chaque nuit, 00 h 30 |
| Rappel des demandes en attente (`omit:remind-pending-requests`) | du lundi au vendredi, 07 h 30 |
| Sauvegarde (`omit:backup`) | chaque soir, 23 h 30 |

La file d'attente envoie les emails et les SMS. En cas de coupure d'Internet, les envois sont réessayés pendant trois jours. Les alertes dans l'application ne dépendent pas d'Internet.

## 8. Optimiser et créer le premier compte

```bash
php artisan optimize
php artisan omit:create-admin informatique@omit.mg --name="Administrateur informatique"
```

La commande affiche un mot de passe provisoire, à changer à la première connexion. L'administrateur crée ensuite les comptes RH depuis l'écran « Comptes ». Il n'a pas accès aux données du personnel : ce sont les RH qui créent les fiches et importent les données existantes (écran « Personnel », puis « Importer »).

## 9. SMS

Par défaut, les SMS sont seulement écrits dans le journal (`storage/logs/laravel.log`), car le fournisseur n'est pas encore choisi. Une fois le fournisseur choisi, un développeur écrit une classe qui implémente `App\Contracts\SmsGateway` et l'indique dans `OMIT_SMS_GATEWAY`. Le reste de l'application ne change pas.

## 10. Vérifications avant l'ouverture

- [ ] `https://rh.omit.local` s'ouvre depuis un poste agent, un poste RH et le poste du Médecin-Chef.
- [ ] `http://` redirige vers `https://`.
- [ ] `APP_DEBUG=false` : une adresse inexistante affiche une page d'erreur neutre.
- [ ] `php artisan schedule:list` liste les cinq tâches ; `systemctl status omit-queue` est actif.
- [ ] `php artisan omit:backup` réussit et l'archive est copiée sur le second support.
- [ ] Une restauration a été testée sur un serveur de test (voir [EXPLOITATION.md](EXPLOITATION.md)).
- [ ] Un email de test est reçu, par exemple en envoyant une demande d'essai.
- [ ] Le port MySQL (3306) n'est pas joignable depuis le réseau.

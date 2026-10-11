#!/usr/bin/env bash
# Crée la base MySQL et l'utilisateur de l'application à partir des valeurs du fichier .env.
# À lancer une fois, avec un compte administrateur MySQL :  sudo ./scripts/creer-base.sh
set -euo pipefail

cd "$(dirname "$0")/.."

lire_env() {
    grep -E "^$1=" .env | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

DB_DATABASE=$(lire_env DB_DATABASE)
DB_USERNAME=$(lire_env DB_USERNAME)
DB_PASSWORD=$(lire_env DB_PASSWORD)

if [[ -z "$DB_DATABASE" || -z "$DB_USERNAME" || -z "$DB_PASSWORD" ]]; then
    echo "DB_DATABASE, DB_USERNAME et DB_PASSWORD doivent être renseignés dans .env" >&2
    exit 1
fi

# Privilèges limités à la base de l'application. En production, les droits de structure
# (CREATE, ALTER, DROP, INDEX, REFERENCES) peuvent être retirés hors des mises à jour.
mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USERNAME}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
    ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "Base « ${DB_DATABASE} » et utilisateur « ${DB_USERNAME} » prêts."
echo "Étape suivante : php artisan migrate"

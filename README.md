# OMIT — Gestion du personnel

Application web interne de l'Organisation Médicale Interentreprise de Toliara. Elle couvre :

- les dossiers du personnel ;
- les congés et leurs soldes ;
- les permissions et les billets de sortie, avec le circuit de validation RH → Médecin-Chef → finalisation ;
- les notifications, les documents PDF, les rapports et les sauvegardes.

Le cahier des charges de référence est `GESTION_DE_PERSONNELS_OMIT_v2.1.md`, dans le dossier parent.

## Documentation

| Document | Pour qui |
|---|---|
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Administrateur informatique : mise en production |
| [docs/EXPLOITATION.md](docs/EXPLOITATION.md) | Administrateur informatique : sauvegardes, restauration, mises à jour |
| [docs/RECETTE.md](docs/RECETTE.md) | OMIT : cahier de recette et procès-verbal |
| [docs/guides/AGENT.md](docs/guides/AGENT.md) | Tout le personnel |
| [docs/guides/RH.md](docs/guides/RH.md) | Ressources Humaines |
| [docs/guides/MEDECIN-CHEF.md](docs/guides/MEDECIN-CHEF.md) | Médecin-Chef |
| [docs/guides/ADMINISTRATEUR.md](docs/guides/ADMINISTRATEUR.md) | Administrateur de l'application |

## Développement

Laravel 13 (PHP 8.4), React 19 avec Vite et Tailwind CSS 4, MySQL 8 (SQLite possible en développement).

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan omit:create-admin admin@exemple.mg
composer run dev          # http://localhost:8000
php artisan test          # tests automatisés
```

L'API est sous `/api/v1`, avec une authentification Sanctum par cookie de session. Les règles métier sont dans `app/Services` :

- `RequestWorkflow` : circuit et statuts des demandes ;
- `LeaveBalanceService` : registre des soldes ;
- `LeaveDayCounter` : décompte des jours.

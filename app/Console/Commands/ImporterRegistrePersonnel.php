<?php

namespace App\Console\Commands;

use App\Services\Import\RegistreImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('omit:import-registre {fichier : Registre des personnels (.ods ou .xls)} {--feuille=REGISTRE : Feuille à lire} {--dry-run : Affiche ce qui serait créé, sans rien enregistrer}')]
#[Description('Ajoute au personnel les agents du registre qui n\'ont pas encore de fiche (matricule = N° IM)')]
class ImporterRegistrePersonnel extends Command
{
    public function handle(RegistreImporter $importer): int
    {
        try {
            $result = $importer->import($this->argument('fichier'), $this->option('feuille'), (bool) $this->option('dry-run'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Matricule', 'Nom', 'Prénoms', 'Fonction', 'Service', 'Embauche', 'Statut'],
            array_map(fn (array $row): array => [$row['matricule'], $row['nom'], $row['prenom'], $row['position'], $row['service'], $row['hire_date'], $row['departed'] ? 'inactif (départ)' : 'actif'], $result['a_creer']),
        );

        $this->line("{$result['lus']} agent(s) dans le registre, {$result['existants']} déjà présent(s), ".count($result['a_creer']).' à ajouter.');

        if ($result['nouveaux_services'] !== []) {
            $this->line('Services à créer : '.implode(', ', $result['nouveaux_services']));
        }
        foreach ($result['differences'] as $difference) {
            $this->warn($difference);
        }
        foreach ($result['erreurs'] as $erreur) {
            $this->error($erreur);
        }

        if ($result['erreurs'] !== []) {
            $this->error('Import annulé : corrigez le registre ou les règles de service.');

            return self::FAILURE;
        }

        $this->info($this->option('dry-run') ? 'Simulation terminée : rien n\'a été enregistré.' : "{$result['crees']} fiche(s) créée(s).");

        return self::SUCCESS;
    }
}

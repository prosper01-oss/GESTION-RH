<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('omit:create-admin {email : Adresse email du compte} {--name=Administrateur : Nom affiché}')]
#[Description("Crée un compte d'administrateur informatique avec un mot de passe provisoire")]
class CreateAdministrator extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $validator = Validator::make(
            ['email' => $this->argument('email'), 'name' => $this->option('name')],
            ['email' => ['required', 'email', 'unique:users,email'], 'name' => ['required', 'string', 'max:255']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $temporaryPassword = Str::password(16, symbols: false);

        $user = DB::transaction(function () use ($temporaryPassword): User {
            $user = User::create([
                'name' => $this->option('name'),
                'email' => $this->argument('email'),
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ]);
            $user->roles()->attach(Role::where('name', RoleName::Administrateur->value)->firstOrFail());

            AuditLog::record('user.created', $user, newValues: ['email' => $user->email, 'roles' => [RoleName::Administrateur->value]]);

            return $user;
        });

        $this->info("Compte administrateur créé : {$user->email}");
        $this->line("Mot de passe provisoire (à changer à la première connexion) : {$temporaryPassword}");

        return self::SUCCESS;
    }
}

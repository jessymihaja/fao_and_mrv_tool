<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\FaqSeeder;
use Database\Seeders\ProvincesAndRegionsSeeder;
use Database\Seeders\ReferenceTablesSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Aucun mot de passe n'est écrit en clair dans le code source (ni dans
     * l'historique git). Un mot de passe aléatoire fort est généré à chaque
     * exécution et affiché UNE SEULE FOIS dans la console — à noter
     * immédiatement et à changer dès la première connexion.
     *
     * updateOrCreate() plutôt que create() : ce seeder est idempotent, il
     * peut être rejoué sans provoquer d'erreur de contrainte unique sur
     * l'email si des comptes existent déjà (seul le mot de passe des
     * comptes déjà existants n'est PAS régénéré à chaque rejeu — voir
     * commentaire dans la boucle).
     */
    public function run(): void
    {
        $this->command->info('→ Création des comptes administrateurs…');

        $comptes = [
            ['name' => 'Super Administrateur', 'email' => 'superadmin@gcf-madagascar.org', 'role' => 'super_admin'],
            ['name' => 'Administrateur GCF',   'email' => 'admin@gcf-madagascar.org',       'role' => 'admin'],
            ['name' => 'Jean-Pierre Rakoto',   'email' => 'gestionnaire@gcf-madagascar.org', 'role' => 'gestionnaire'],
        ];

        $identifiants = [];

        foreach ($comptes as $compte) {
            $dejaExistant = User::where('email', $compte['email'])->exists();
            $motDePasse   = Str::password(16);

            User::updateOrCreate(
                ['email' => $compte['email']],
                array_filter([
                    'name'      => $compte['name'],
                    'role'      => $compte['role'],
                    'is_active' => true,
                    // Ne réinitialise le mot de passe QUE lors de la toute
                    // première création — un rejeu du seeder sur une base
                    // existante ne doit pas déconnecter silencieusement les
                    // comptes déjà en service.
                    'password'  => $dejaExistant ? null : Hash::make($motDePasse),
                ], fn ($v) => $v !== null)
            );

            if (! $dejaExistant) {
                $identifiants[] = "  {$compte['role']} — {$compte['email']} : {$motDePasse}";
            }
        }

        if ($identifiants) {
            $this->command->warn('');
            $this->command->warn('⚠️  Mots de passe générés (affichés UNE SEULE FOIS — à noter immédiatement) :');
            foreach ($identifiants as $ligne) {
                $this->command->warn($ligne);
            }
            $this->command->warn('⚠️  Changez ces mots de passe dès la première connexion.');
            $this->command->warn('');
        } else {
            $this->command->info('→ Comptes administrateurs déjà existants, mots de passe inchangés.');
        }

        // ─── GÉOGRAPHIE (provinces & régions de Madagascar) ────
        // Gap fonctionnel corrigé lors de l'audit : ce seeder existait mais
        // n'était jamais appelé depuis DatabaseSeeder — `migrate:fresh --seed`
        // laissait `provinces`/`regions` vides alors que `projects` et
        // `project_ideas` peuvent y faire référence par FK.
        $this->command->info('→ Insertion des provinces et régions…');
        $this->call(ProvincesAndRegionsSeeder::class);

        // ─── TABLES DE RÉFÉRENCE ──────────────────────────────
        $this->command->info('→ Insertion des tables de référence…');
        $this->call(ReferenceTablesSeeder::class);

        // ─── DEVISES ───────────────────────────────────────────
        $this->command->info('→ Insertion des devises…');
        $this->call(CurrencySeeder::class);

        // ─── FAQ ─────────────────────────────────────────────
        $this->command->info('→ Insertion des FAQs…');
        $this->call(FaqSeeder::class);
    }
}

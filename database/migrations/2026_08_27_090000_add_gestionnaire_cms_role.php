<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le rôle "gestionnaire_cms" — un rôle limité à la gestion du site
 * vitrine (CMS : FAQ, partenaires, contacts, slider, chatbot, paramètres
 * publics) — sans aucun accès aux données métier (projets, financements,
 * rapports, parties prenantes, utilisateurs, etc.).
 *
 * La colonne "role" est un $table->enum(...) Postgres, donc en réalité une
 * simple colonne texte + une contrainte CHECK nommée "users_role_check". On
 * ne peut pas "ajouter une valeur" à un enum Postgres avec Schema::enum() ;
 * on supprime l'ancienne contrainte et on la recrée avec la liste complète.
 */
return new class extends Migration
{
    private const ROLES = ['super_admin', 'admin', 'gestionnaire', 'gestionnaire_cms', 'utilisateur'];

    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        $list = "'" . implode("','", self::ROLES) . "'";
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ({$list}))");
    }

    public function down(): void
    {
        // Repasser un utilisateur "gestionnaire_cms" existant en
        // "utilisateur" (lecture seule) avant de retirer la valeur de la
        // contrainte, pour ne pas laisser de ligne orpheline invalide.
        DB::table('users')->where('role', 'gestionnaire_cms')->update(['role' => 'utilisateur']);

        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        $previous = ['super_admin', 'admin', 'gestionnaire', 'utilisateur'];
        $list = "'" . implode("','", $previous) . "'";
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ({$list}))");
    }
};

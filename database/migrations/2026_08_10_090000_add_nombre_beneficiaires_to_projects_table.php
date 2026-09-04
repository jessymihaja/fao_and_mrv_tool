<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La Homepage doit afficher un "Nombre de bénéficiaires" calculé
     * automatiquement — aucun champ structuré n'existe pour ça sur Project
     * (seul le module Idées de projet en a un). Ajouté ici avec le même nom
     * que sur ProjectIdea, pour rester cohérent, et pouvoir être renseigné
     * automatiquement lors d'une future conversion Idée → Projet.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('nombre_beneficiaires')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('nombre_beneficiaires');
        });
    }
};

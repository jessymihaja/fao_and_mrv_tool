<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une activité peut désormais être rattachée directement à un projet
     * ("Activités du projet", étape 3 du Wizard) OU à une composante
     * ("Activités de la composante", étape 4). Jamais les deux à la fois —
     * règle appliquée au niveau applicatif (ActiviteController).
     */
    public function up(): void
    {
        // 1. Ajouter project_id (nullable)
        Schema::table('activites', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id')
                ->constrained('projects')->cascadeOnDelete();
        });

        // 2. Rendre composante_id nullable : on recrée la contrainte de clé
        //    étrangère puis on modifie la colonne nativement via ->change()
        //    (pas besoin de doctrine/dbal depuis Laravel 11+).
        Schema::table('activites', function (Blueprint $table) {
            $table->dropForeign(['composante_id']);
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->foreignId('composante_id')->nullable()->change();
            $table->foreign('composante_id')->references('id')->on('composantes')->cascadeOnDelete();
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->index(['project_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::table('activites', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'statut']);
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->dropForeign(['composante_id']);
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->foreignId('composante_id')->nullable(false)->change();
            $table->foreign('composante_id')->references('id')->on('composantes')->cascadeOnDelete();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute un champ "Lien" (URL externe : concept note en ligne, dossier
     * partagé, page du bailleur...) juste après le titre, affiché dans le
     * formulaire (Onglet 1) et dans le tableau de la liste des idées.
     */
    public function up(): void
    {
        Schema::table('project_ideas', function (Blueprint $table) {
            $table->string('lien', 500)->nullable()->after('titre');
        });
    }

    public function down(): void
    {
        Schema::table('project_ideas', function (Blueprint $table) {
            $table->dropColumn('lien');
        });
    }
};

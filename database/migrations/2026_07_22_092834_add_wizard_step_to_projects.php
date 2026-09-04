<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * wizard_step = la dernière étape validée par l'utilisateur (1 à 5).
     * Sert à verrouiller la navigation du Wizard côté frontend :
     * une étape N+1 n'est accessible que si wizard_step >= N.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('wizard_step')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('wizard_step');
        });
    }
};

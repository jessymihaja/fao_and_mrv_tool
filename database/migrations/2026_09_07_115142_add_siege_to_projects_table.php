<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Ajoute le champ siege (string, facultatif/nullable)
            $table->string('siege')->nullable()->after('nom'); 
            // Astuce : vous pouvez ajuster ->after('nom') selon l'emplacement souhaité dans la table
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('siege');
        });
    }
};

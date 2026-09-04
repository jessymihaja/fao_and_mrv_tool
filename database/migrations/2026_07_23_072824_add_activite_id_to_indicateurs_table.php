<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Troisième niveau d'indicateurs : un indicateur peut désormais mesurer
     * l'exécution/les résultats d'une activité précise (projet OU composante),
     * en plus des niveaux existants (projet, composante).
     */
    public function up(): void
    {
        Schema::table('indicateurs', function (Blueprint $table) {
            $table->foreignId('activite_id')->nullable()->after('composante_id')
                ->constrained('activites')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('indicateurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activite_id');
        });
    }
};

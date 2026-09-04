<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Référentiel des organismes contributeurs (co-financeurs), partagé
     * entre tous les financements. Même fonctionnement que Classification :
     * liste + bouton "+" pour en ajouter un nouveau à la volée depuis le
     * formulaire (SelectAvecAjout).
     */
    public function up(): void
    {
        Schema::create('organismes_contributeurs', function (Blueprint $table) {
            $table->id();
            $table->string('designation')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organismes_contributeurs');
    }
};

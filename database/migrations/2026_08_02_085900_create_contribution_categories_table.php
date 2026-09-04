<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Référentiel extensible des catégories de contribution en nature
     * (Terrain, Bâtiment, Machine/Équipement, ...). Même fonctionnement que
     * Classification / Statut : liste de base + bouton "+" pour en ajouter
     * une nouvelle directement depuis le formulaire (SelectAvecAjout).
     */
    public function up(): void
    {
        Schema::create('contribution_categories', function (Blueprint $table) {
            $table->id();
            $table->string('designation')->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('contribution_categories')->insert(collect([
            'Terrain', 'Bâtiment', 'Machine / Équipement', 'Véhicule',
            'Personnel', 'Expertise technique', 'Logiciel', 'Mobilier',
        ])->map(fn ($designation) => [
            'designation' => $designation,
            'created_at'  => $now,
            'updated_at'  => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_categories');
    }
};

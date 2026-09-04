<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ============================================================
// 2026_08_18_090000_create_project_zone_points_table.php
//
// Stocke les sommets du polygone délimitant la zone d'intervention
// d'un projet. Remplace la sélection à point unique (latitude /
// longitude sur `projects`) par une liste ordonnée de points reliés
// entre eux pour former le polygone officiel de la zone.
//
// Les colonnes latitude/longitude existantes sur `projects` sont
// conservées pour la rétrocompatibilité (localisation "principale"
// du projet, adresse inversée, cascade province/région/district…) ;
// cette table les complète sans les remplacer.
// ============================================================

return new class extends Migration {
    public function up(): void {
        Schema::create('project_zone_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            // Position du point dans le polygone (ordre de création / affichage)
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'ordre']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('project_zone_points');
    }
};

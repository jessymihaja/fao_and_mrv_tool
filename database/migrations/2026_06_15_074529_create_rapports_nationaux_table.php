<?php
// database/migrations/2024_01_01_000002_create_rapports_nationaux_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapports_nationaux', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->unsignedSmallInteger('annee')->nullable()->index();

            // Filtres de périmètre
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('secteur_climatique')->nullable();
            $table->string('accredited_entity')->nullable();
            $table->string('source_financement')->nullable();
            $table->string('statut_projet')->nullable();

            $table->enum('statut', ['brouillon', 'genere', 'publie'])->default('brouillon');

            // Contenu JSON généré (résumé, financier, physique, climatique)
            $table->json('contenu')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports_nationaux');
    }
};
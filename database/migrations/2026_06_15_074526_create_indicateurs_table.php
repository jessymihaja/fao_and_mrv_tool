<?php
// database/migrations/2024_01_01_000001_create_indicateurs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicateurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->enum('categorie', ['financier', 'physique', 'adaptation', 'attenuation']);
            $table->string('nom');
            $table->string('unite', 50);
            $table->decimal('valeur_cible',    15, 4);
            $table->decimal('valeur_realisee', 15, 4)->default(0);
            // Champs calculés (mis à jour automatiquement via observer/mutator)
            $table->decimal('taux_atteinte', 8, 4)->default(0)->comment('(réalisée/cible)*100');
            $table->decimal('ecart',         15, 4)->default(0)->comment('réalisée - cible');
            $table->enum('niveau_performance', ['Faible', 'Moyen', 'Bon', 'Excellent'])->default('Faible');
            $table->date('date_reference');
            $table->text('commentaire')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'categorie']);
            $table->index('date_reference');
        });

        Schema::create('indicateur_justificatifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicateur_id')->constrained('indicateurs')->cascadeOnDelete();
            $table->string('fichier');           // chemin stockage
            $table->string('nom_original');
            $table->unsignedBigInteger('taille')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicateur_justificatifs');
        Schema::dropIfExists('indicateurs');
    }
};

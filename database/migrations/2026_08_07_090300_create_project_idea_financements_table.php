<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onglet 6 : Financement envisagé — plusieurs bailleurs possibles par
     * idée de projet (GCF, Banque mondiale, BAD, PNUD, FEM, UE, AFD, JICA,
     * Autre...). Réutilise le référentiel organismes_contributeurs déjà en
     * place pour le module Financement (mêmes bailleurs, ajout en ligne
     * déjà supporté), plutôt que d'en créer un doublon.
     */
    public function up(): void
    {
        Schema::create('project_idea_financements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisme_contributeur_id')->nullable()->constrained('organismes_contributeurs')->nullOnDelete();
            $table->string('bailleur_autre')->nullable(); // libellé libre si organisme non référencé
            $table->decimal('montant_demande', 18, 2)->nullable();
            $table->enum('devise', ['AR', 'USD', 'EUR'])->default('AR');
            $table->enum('type_financement', ['don', 'pret', 'cofinancement', 'assistance_technique'])->default('don');
            $table->enum('statut', ['en_preparation', 'soumis', 'en_negociation'])->default('en_preparation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_idea_financements');
    }
};

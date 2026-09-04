<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Section Homepage "Perspectives des projets" : plutôt que d'afficher
     * du texte statique ("objectifs à moyen/long terme", "zones
     * d'extension envisagées"...) qui n'existe nulle part dans le schéma
     * actuel, cette table capture ces informations de façon structurée,
     * projet par projet, gérées depuis un nouvel onglet "Perspectives" sur
     * la fiche projet — pour que la section Homepage affiche de vraies
     * données saisies par l'équipe plutôt que du contenu inventé.
     */
    public function up(): void
    {
        Schema::create('project_perspectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['extension', 'perennisation', 'nouveau_financement', 'autre'])->default('autre');
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('zone_extension_envisagee')->nullable();
            $table->text('objectif_moyen_terme')->nullable();
            $table->text('objectif_long_terme')->nullable();
            $table->text('impact_futur_attendu')->nullable();
            $table->enum('statut', ['a_l_etude', 'planifie', 'en_cours', 'realise'])->default('a_l_etude');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_perspectives');
    }
};

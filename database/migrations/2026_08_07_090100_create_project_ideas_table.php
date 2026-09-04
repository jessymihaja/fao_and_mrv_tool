<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module "Idées de projet" — indépendant du module Projet, mais relié à
     * lui pour la conversion (voir migration
     * add_project_idea_id_to_projects_table). Gère tout le cycle de
     * préparation d'un projet avant son approbation officielle :
     * Brouillon → Soumis → En étude → Approuvé → Converti en Projet.
     */
    public function up(): void
    {
        Schema::create('project_ideas', function (Blueprint $table) {
            $table->id();

            // ── Onglet 1 : Informations générales ───────────────────────────
            $table->string('titre');
            $table->string('acronyme')->nullable();
            $table->text('description')->nullable();
            $table->text('contexte')->nullable();
            $table->text('justification')->nullable();
            $table->text('objectif_general')->nullable();
            $table->text('objectifs_specifiques')->nullable();
            $table->text('resultats_attendus')->nullable();
            $table->unsignedSmallInteger('duree_prevue_mois')->nullable();
            $table->date('date_debut_estimee')->nullable();
            $table->date('date_fin_estimee')->nullable();
            $table->string('porteur_projet')->nullable();

            // ── Onglet 2 : Localisation (mêmes champs que Project, pour
            //    réutiliser tel quel le composant GeoZoneSelector) ─────────
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fokontany_id')->nullable()->constrained('fokontany')->nullOnDelete();
            $table->text('zone_description')->nullable();
            $table->string('geo_address')->nullable();

            // ── Onglet 4 : Bénéficiaires ─────────────────────────────────────
            $table->unsignedInteger('nombre_beneficiaires')->nullable();
            $table->unsignedInteger('beneficiaires_hommes')->nullable();
            $table->unsignedInteger('beneficiaires_femmes')->nullable();
            $table->unsignedInteger('beneficiaires_jeunes')->nullable();
            $table->unsignedInteger('beneficiaires_vulnerables')->nullable();

            // ── Onglet 5 : Budget prévisionnel ───────────────────────────────
            $table->decimal('budget_total_estime', 18, 2)->nullable();
            $table->enum('devise', ['AR', 'USD', 'EUR'])->default('AR');
            $table->decimal('contribution_nationale', 18, 2)->nullable();
            $table->decimal('contribution_partenaires', 18, 2)->nullable();
            $table->decimal('cofinancement_prive', 18, 2)->nullable();
            $table->decimal('autres_financements', 18, 2)->nullable();

            // ── Workflow ──────────────────────────────────────────────────
            $table->enum('statut', ['brouillon', 'soumis', 'en_etude', 'approuve', 'converti'])->default('brouillon');

            // ── Conversion en Projet (traçabilité) ───────────────────────────
            $table->foreignId('converted_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_ideas');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module "Parties prenantes" (Stakeholders) — Phase 1.
     *
     * Module indépendant : à ce stade, une partie prenante n'est liée ni à
     * une Idée de projet ni à un Projet (voir cahier des charges). Pour
     * rester facilement évolutif sans verrouiller aujourd'hui une
     * cardinalité (une partie prenante pourra un jour intervenir sur
     * plusieurs projets/idées à la fois), cette table ne porte
     * volontairement AUCUNE clé étrangère vers `projects` ou
     * `project_ideas`. La liaison future se fera par de simples tables
     * pivots (`project_stakeholder`, `project_idea_stakeholder`) ajoutées
     * dans une prochaine migration, sans jamais avoir à modifier celle-ci.
     */
    public function up(): void
    {
        Schema::create('stakeholders', function (Blueprint $table) {
            $table->id();

            // ── Informations générales ────────────────────────────────────
            $table->string('nom');
            $table->string('organisation')->nullable();
            $table->string('acronyme')->nullable();

            $table->foreignId('categorie_id')->constrained('stakeholder_categories')->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('stakeholder_roles')->nullOnDelete();

            // ── Coordonnées ────────────────────────────────────────────────
            $table->string('nom_representant')->nullable();
            $table->string('fonction')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->text('adresse')->nullable();

            // ── Contribution ───────────────────────────────────────────────
            $table->foreignId('type_contribution_id')->nullable()->constrained('stakeholder_contribution_types')->nullOnDelete();
            $table->text('description_contribution')->nullable();
            $table->decimal('montant_estimatif', 18, 2)->nullable();
            $table->enum('devise', ['AR', 'USD', 'EUR'])->nullable();

            // ── Participation ──────────────────────────────────────────────
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->enum('statut', ['actif', 'en_attente', 'suspendu', 'termine'])->default('actif');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stakeholders');
    }
};

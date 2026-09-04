<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 6 de l'audit BDD (§M-1). Concrétise la Phase 2 annoncée dans le
 * commentaire de 2026_08_09_090100_create_stakeholders_table : relie enfin
 * le module Parties prenantes au reste de l'application, sans jamais avoir
 * eu à modifier la table `stakeholders` elle-même (comme prévu dès
 * l'origine). Purement additif — n'affecte aucune donnée existante, ne
 * retire aucune fonctionnalité déjà en service.
 *
 * N:N dans les deux sens (une partie prenante peut intervenir sur plusieurs
 * projets/idées ; un projet/une idée peut avoir plusieurs parties
 * prenantes), avec une colonne `role_specifique` optionnelle pour préciser
 * le rôle de cette partie prenante spécifiquement sur ce projet (distinct
 * de `stakeholder_roles`, qui décrit son rôle générique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_stakeholder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stakeholder_id')->constrained()->cascadeOnDelete();
            $table->string('role_specifique')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'stakeholder_id']);
        });

        Schema::create('project_idea_stakeholder', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stakeholder_id')->constrained()->cascadeOnDelete();
            $table->string('role_specifique')->nullable();
            $table->timestamps();

            $table->unique(['project_idea_id', 'stakeholder_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_idea_stakeholder');
        Schema::dropIfExists('project_stakeholder');
    }
};

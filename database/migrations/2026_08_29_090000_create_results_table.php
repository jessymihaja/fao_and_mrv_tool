<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ============================================================
// 2026_08_29_090000_create_results_table.php
//
// Module « Résultats du projet » (cadre logique GCF : Impact /
// Outcome / Output). Le projet est TOUJOURS déduit de la route
// (/projects/{project}/results) — jamais saisi dans le formulaire.
//
// Anti-duplication (audit §4) : `valeur_cible`, `valeur_realisee`,
// `unite` et `date_reference` (année de réalisation) NE SONT PAS
// dupliqués ici. Quand un résultat est associé à un `indicateur_id`,
// ces informations sont lues depuis la table `indicateurs`, seule
// source de vérité — voir Result::indicateur() et
// ResultResource/l'accesseur `taux_atteinte` du modèle. Seuls les
// champs réellement propres au résultat (et absents du modèle
// Indicateur) sont stockés ici : valeur_reference (baseline),
// source_verification, methode_collecte.
// ============================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            // Rattachement optionnel, cohérent avec le modèle déjà en place pour
            // les indicateurs (project_id direct, ou via composante/activité).
            $table->foreignId('composante_id')->nullable()->constrained('composantes')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->constrained('activites')->nullOnDelete();
            // Réutilisation de l'indicateur existant plutôt que de dupliquer
            // cible/réalisé/unité (audit §4).
            $table->foreignId('indicateur_id')->nullable()->constrained('indicateurs')->nullOnDelete();

            $table->enum('type', ['impact', 'outcome', 'output']);
            $table->string('titre');
            $table->text('description')->nullable();

            $table->smallInteger('reference_year');
            $table->smallInteger('target_year');

            $table->enum('statut', ['prevu', 'en_cours', 'atteint', 'partiellement_atteint', 'non_atteint'])
                  ->default('prevu');

            // Champs propres au résultat, absents du modèle Indicateur.
            $table->decimal('valeur_reference', 15, 4)->nullable();
            $table->string('source_verification')->nullable();
            $table->string('methode_collecte')->nullable();

            // Suivi
            $table->text('observations')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'type']);
            $table->index(['project_id', 'statut']);
        });

        DB::statement('ALTER TABLE results ADD CONSTRAINT chk_results_target_year CHECK (target_year >= reference_year)');
        DB::statement('ALTER TABLE results ADD CONSTRAINT chk_results_valeur_reference CHECK (valeur_reference IS NULL OR valeur_reference >= 0)');

        Schema::create('result_pieces_jointes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_id')->constrained('results')->cascadeOnDelete();
            $table->string('fichier');           // chemin stockage (disque privé 'local')
            $table->string('nom_original');
            $table->unsignedBigInteger('taille')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_pieces_jointes');
        Schema::dropIfExists('results');
    }
};

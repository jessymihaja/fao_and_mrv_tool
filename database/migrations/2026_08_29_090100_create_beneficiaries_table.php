<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ============================================================
// 2026_08_29_090100_create_beneficiaries_table.php
//
// Module « Bénéficiaires du projet ». Le projet est TOUJOURS déduit
// de la route (/projects/{project}/beneficiaries) — jamais saisi
// dans le formulaire.
//
// Anti-duplication (audit §4/§7) : aucune nouvelle table géographique
// n'est créée. Les colonnes region_id/district_id/commune_id/
// fokontany_id pointent vers les tables de référence géographiques
// déjà utilisées par `projects` (mêmes modèles Region/District/
// Commune/Fokontany) ; le frontend propose par défaut de réutiliser
// la zone déjà enregistrée sur le projet plutôt que de la resaisir.
// ============================================================

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            $table->enum('type', ['direct', 'indirect']);
            $table->enum('category', [
                'agriculteurs', 'pecheurs', 'menages', 'communautes_locales',
                'femmes', 'jeunes', 'entreprises', 'organisations_communautaires',
                'institutions_publiques', 'ong_osc', 'autre',
            ]);
            $table->text('description')->nullable();

            // Localisation — réutilisation des tables géographiques existantes
            // (voir Project::region()/district()/commune()/fokontany()), jamais
            // une nouvelle table dupliquée.
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained('communes')->nullOnDelete();
            $table->foreignId('fokontany_id')->nullable()->constrained('fokontany')->nullOnDelete();

            $table->integer('planned_count')->default(0);
            $table->integer('achieved_count')->default(0);
            $table->integer('women_count')->nullable();
            $table->integer('men_count')->nullable();
            $table->integer('youth_count')->nullable();
            $table->integer('vulnerable_count')->nullable();

            $table->smallInteger('reference_year');
            $table->smallInteger('monitoring_year')->nullable();

            // Calculé automatiquement avant sauvegarde (voir Beneficiary::booted()) —
            // jamais saisi manuellement côté frontend.
            $table->decimal('taux_atteinte', 8, 4)->default(0);

            $table->string('source')->nullable();
            $table->text('observations')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'type']);
            $table->index(['project_id', 'category']);
        });

        DB::statement('ALTER TABLE beneficiaries ADD CONSTRAINT chk_beneficiaries_planned_count CHECK (planned_count >= 0)');
        DB::statement('ALTER TABLE beneficiaries ADD CONSTRAINT chk_beneficiaries_achieved_count CHECK (achieved_count >= 0)');
        DB::statement('ALTER TABLE beneficiaries ADD CONSTRAINT chk_beneficiaries_women_count CHECK (women_count IS NULL OR women_count >= 0)');
        DB::statement('ALTER TABLE beneficiaries ADD CONSTRAINT chk_beneficiaries_men_count CHECK (men_count IS NULL OR men_count >= 0)');
        DB::statement('ALTER TABLE beneficiaries ADD CONSTRAINT chk_beneficiaries_youth_count CHECK (youth_count IS NULL OR youth_count >= 0)');
        DB::statement('ALTER TABLE beneficiaries ADD CONSTRAINT chk_beneficiaries_vulnerable_count CHECK (vulnerable_count IS NULL OR vulnerable_count >= 0)');
        DB::statement('ALTER TABLE beneficiaries ADD CONSTRAINT chk_beneficiaries_taux_atteinte CHECK (taux_atteinte >= 0)');
        // Désagrégation complète (femmes + hommes) : ne doit pas dépasser le nombre
        // atteint quand les deux valeurs sont renseignées (règle métier §13).
        DB::statement(
            'ALTER TABLE beneficiaries ADD CONSTRAINT chk_beneficiaries_gender_breakdown '
            . 'CHECK (women_count IS NULL OR men_count IS NULL OR (women_count + men_count) <= achieved_count)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── 1. Tables pivots ────────────────────────────────────────────────
        Schema::create('project_classification', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classification_id')
                  ->constrained('classifications', 'id_classification')
                  ->cascadeOnDelete();
            $table->primary(['project_id', 'classification_id']);
        });

        Schema::create('project_entite_accreditee', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entite_accreditee_id')
                  ->constrained('entite_accreditees', 'id_entite_accreditee')
                  ->cascadeOnDelete();
            $table->primary(['project_id', 'entite_accreditee_id']);
        });

        Schema::create('project_domaine_intervention', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domaine_intervention_id')
                  ->constrained('domaine_interventions', 'id_domaine_intervention')
                  ->cascadeOnDelete();
            $table->primary(['project_id', 'domaine_intervention_id']);
        });

        // ── 2. Migration des données existantes (aucune perte) ─────────────
        DB::table('projects')
            ->whereNotNull('classification_id')
            ->select('id', 'classification_id')
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                DB::table('project_classification')->insert(
                    $rows->map(fn ($r) => [
                        'project_id'        => $r->id,
                        'classification_id' => $r->classification_id,
                    ])->all()
                );
            });

        DB::table('projects')
            ->whereNotNull('entite_accreditee_id')
            ->select('id', 'entite_accreditee_id')
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                DB::table('project_entite_accreditee')->insert(
                    $rows->map(fn ($r) => [
                        'project_id'            => $r->id,
                        'entite_accreditee_id'  => $r->entite_accreditee_id,
                    ])->all()
                );
            });

        DB::table('projects')
            ->whereNotNull('domaine_intervention_id')
            ->select('id', 'domaine_intervention_id')
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                DB::table('project_domaine_intervention')->insert(
                    $rows->map(fn ($r) => [
                        'project_id'               => $r->id,
                        'domaine_intervention_id'  => $r->domaine_intervention_id,
                    ])->all()
                );
            });

        // ── 3. Suppression des anciennes colonnes FK simples ────────────────
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['classification_id']);
            $table->dropForeign(['entite_accreditee_id']);
            $table->dropForeign(['domaine_intervention_id']);
            $table->dropColumn(['classification_id', 'entite_accreditee_id', 'domaine_intervention_id']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('classification_id')->nullable()
                  ->constrained('classifications', 'id_classification')->nullOnDelete();
            $table->foreignId('entite_accreditee_id')->nullable()
                  ->constrained('entite_accreditees', 'id_entite_accreditee')->nullOnDelete();
            $table->foreignId('domaine_intervention_id')->nullable()
                  ->constrained('domaine_interventions', 'id_domaine_intervention')->nullOnDelete();
        });

        // Reprend la première valeur pivot trouvée pour chaque projet (best-effort)
        foreach (DB::table('project_classification')->orderBy('project_id')->get()->groupBy('project_id') as $pid => $rows) {
            DB::table('projects')->where('id', $pid)->update(['classification_id' => $rows->first()->classification_id]);
        }
        foreach (DB::table('project_entite_accreditee')->orderBy('project_id')->get()->groupBy('project_id') as $pid => $rows) {
            DB::table('projects')->where('id', $pid)->update(['entite_accreditee_id' => $rows->first()->entite_accreditee_id]);
        }
        foreach (DB::table('project_domaine_intervention')->orderBy('project_id')->get()->groupBy('project_id') as $pid => $rows) {
            DB::table('projects')->where('id', $pid)->update(['domaine_intervention_id' => $rows->first()->domaine_intervention_id]);
        }

        Schema::dropIfExists('project_classification');
        Schema::dropIfExists('project_entite_accreditee');
        Schema::dropIfExists('project_domaine_intervention');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('status_id')
                  ->nullable()
                  ->constrained('statuses', 'id_status')
                  ->nullOnDelete();

            $table->foreignId('classification_id')
                  ->nullable()
                  ->constrained('classifications', 'id_classification')
                  ->nullOnDelete();

            $table->foreignId('domaine_intervention_id')
                  ->nullable()
                  ->constrained('domaine_interventions', 'id_domaine_intervention')
                  ->nullOnDelete();

            $table->foreignId('entite_accreditee_id')
                  ->nullable()
                  ->constrained('entite_accreditees', 'id_entite_accreditee')
                  ->nullOnDelete();
        });

        // Migrer les données existantes : texte → FK
        $statuts = DB::table('statuses')->pluck('id_status', 'designation');
        $classifications = DB::table('classifications')->pluck('id_classification', 'designation');
        $domaines = DB::table('domaine_interventions')->pluck('id_domaine_intervention', 'designation');
        $entites = DB::table('entite_accreditees')->pluck('id_entite_accreditee', 'designation');

        // Map anciens champs enum/texte vers les nouvelles FK
        $statutMap = [
            'Concept Note'     => $statuts->get('Concept Note'),
            'Funding Proposal' => $statuts->get('Funding Proposal'),
            'En cours'         => $statuts->get('En cours'),
            'Clôturé'          => $statuts->get('Clôturé'),
        ];

        foreach (DB::table('projects')->get() as $project) {
            DB::table('projects')->where('id', $project->id)->update([
                'status_id'              => $statutMap[$project->statut] ?? null,
                'classification_id'      => $classifications->get($project->classification),
                'domaine_intervention_id'=> $domaines->get($project->secteur_climatique),
                'entite_accreditee_id'   => $entites->get($project->accredited_entity),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropForeign(['classification_id']);
            $table->dropForeign(['domaine_intervention_id']);
            $table->dropForeign(['entite_accreditee_id']);
            $table->dropColumn(['status_id', 'classification_id', 'domaine_intervention_id', 'entite_accreditee_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 4 de l'audit BDD (§17 — Index). Contrairement à MySQL, PostgreSQL
 * ne crée PAS automatiquement d'index sur une colonne de clé étrangère : le
 * `foreign()` déclaré par `foreignId()->constrained()` garantit l'intégrité
 * référentielle mais pas la performance des requêtes qui filtrent sur cette
 * colonne. Cette migration cible les colonnes FK effectivement utilisées en
 * clause WHERE dans les contrôleurs (project_id en tête de liste — présent
 * dans plus d'une dizaine de tables).
 *
 * `Schema::table(...)->index(...)` avec un nom explicite pour éviter tout
 * conflit avec un futur index de même forme ; utilise `->index()` (pas
 * unique) car plusieurs lignes peuvent légitimement partager le même
 * project_id.
 */
return new class extends Migration
{
    /** [table, colonnes] */
    private function targets(): array
    {
        return [
            ['financements', ['project_id']],
            ['depenses', ['project_id', 'financement_id']],
            ['documents', ['project_id', 'financement_id']],
            ['financement_contributions', ['financement_id', 'organisme_contributeur_id']],
            ['budget_pledges', ['financement_id', 'bailleur_id']],
            ['budget_approbations', ['financement_id', 'organisme_id']],
            ['engagements', ['financement_id']],
            ['decaissement_plans', ['financement_id']],
            ['decaissements', ['financement_id']],
            ['activity_logs', ['project_id']],
        ];
    }

    public function up(): void
    {
        foreach ($this->targets() as [$table, $columns]) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    $indexName = "idx_{$table}_{$column}";
                    $blueprint->index($column, $indexName);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets() as [$table, $columns]) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    $blueprint->dropIndex("idx_{$table}_{$column}");
                }
            });
        }
    }
};

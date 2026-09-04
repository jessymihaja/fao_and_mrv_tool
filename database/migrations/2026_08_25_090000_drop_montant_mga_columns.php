<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Suppression du champ "montant_mga" (équivalent en Ariary / MGA) sur
 * l'ensemble du module budgétaire.
 *
 * Ce champ servait de base commune pour additionner des montants saisis
 * dans des devises différentes (USD / EUR / AR). Il est retiré à la
 * demande : les totaux/agrégations utilisent désormais directement la
 * colonne "montant" (ou équivalent) de chaque table, sans conversion de
 * devise.
 *
 * On commence par supprimer les contraintes CHECK qui référencent la
 * colonne (ajoutées par 2026_08_24_080000_add_check_constraints_integrity)
 * avant de supprimer les colonnes elles-mêmes.
 */
return new class extends Migration
{
    /** [table, colonne] concernées par montant_mga */
    private function targets(): array
    {
        return [
            ['financements', 'montant_mga'],
            ['financement_contributions', 'montant_mga'],
            ['budget_pledges', 'montant_mga'],
            ['budget_approbations', 'montant_mga'],
            ['engagements', 'montant_mga'],
            ['decaissement_plans', 'montant_mga'],
            ['decaissements', 'montant_mga'],
            ['depenses', 'montant_mga'],
        ];
    }

    public function up(): void
    {
        // 1) Supprimer les contraintes CHECK existantes sur montant_mga (si présentes).
        foreach ($this->targets() as [$table, $column]) {
            $constraint = "chk_{$table}_{$column}";
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        }

        // 2) Supprimer les colonnes montant_mga.
        foreach ($this->targets() as [$table, $column]) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        // Recréation des colonnes (nullable, sans valeur ré-historisée : la
        // conversion en MGA n'est plus calculée par l'application).
        foreach ($this->targets() as [$table, $column]) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $t) use ($column) {
                    $t->decimal($column, 20, 2)->nullable();
                });
            }
        }
    }
};

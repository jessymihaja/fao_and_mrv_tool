<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Suite de 2026_08_24_080400_create_currencies_table : convertit les 10
 * colonnes `devise` (enum PostgreSQL figé) en `char(3)` + clé étrangère vers
 * `currencies.code`. Le doctrine/dbal n'étant pas installé sur ce projet
 * (cf. commentaires existants dans project_perspectives), la conversion de
 * type se fait en SQL brut (`ALTER COLUMN ... TYPE`), nativement supporté
 * par PostgreSQL sans dépendance supplémentaire.
 *
 * Étapes par table : (1) retirer le DEFAULT le temps de la conversion,
 * (2) convertir le type enum -> varchar(3), (3) ajouter la FK,
 * (4) restaurer le DEFAULT (désormais une simple valeur texte contrainte
 * par la FK plutôt que par le type enum).
 */
return new class extends Migration
{
    /** [table, colonne, valeur par défaut existante ou null] */
    private function targets(): array
    {
        return [
            ['financements', 'devise', 'USD'],
            ['financement_contributions', 'devise', null],
            ['budget_pledges', 'devise', 'AR'],
            ['budget_approbations', 'devise', 'AR'],
            ['engagements', 'devise', 'AR'],
            ['decaissement_plans', 'devise', 'AR'],
            ['decaissements', 'devise', 'AR'],
            ['depenses', 'devise', 'AR'],
            ['project_ideas', 'devise', 'AR'],
            ['project_idea_financements', 'devise', 'AR'],
            ['stakeholders', 'devise', null],
        ];
    }

    public function up(): void
    {
        foreach ($this->targets() as [$table, $column, $default]) {
            $fkName = "fk_{$table}_{$column}_currencies";

            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE varchar(3) USING {$column}::varchar(3)");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$fkName} FOREIGN KEY ({$column}) REFERENCES currencies(code)");

            if ($default !== null) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT '{$default}'");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->targets() as [$table, $column, $default]) {
            $fkName = "fk_{$table}_{$column}_currencies";

            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$fkName}");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE varchar(3)");

            if ($default !== null) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT '{$default}'");
            }
        }
    }
};

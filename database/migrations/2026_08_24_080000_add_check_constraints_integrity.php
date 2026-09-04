<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PHASE 2 de l'audit BDD (§ Intégrité) — ajoute au niveau PostgreSQL les
 * contraintes CHECK qui n'existaient nulle part dans le schéma : jusqu'ici
 * la base faisait entièrement confiance à la validation Laravel (`min:0`,
 * `between:-90,90`...), qui ne protège pas contre un INSERT/UPDATE direct
 * en base, un script de migration de données, ou un futur endpoint qui
 * oublierait la règle.
 *
 * Additive et non destructive : n'échoue que si des données existantes
 * violent déjà une contrainte (auquel cas la migration s'arrête et affiche
 * clairement quelle table/contrainte pose problème — à corriger avant de
 * relancer, plutôt que de contourner silencieusement).
 */
return new class extends Migration
{
    /** [table, colonne, expression CHECK, nom de la contrainte] */
    private function positiveAmountChecks(): array
    {
        return [
            ['financements', 'budget_approuve', 'budget_approuve >= 0'],
            ['financements', 'montant_mga', 'montant_mga >= 0'],
            ['depenses', 'montant', 'montant >= 0'],
            ['depenses', 'montant_mga', 'montant_mga IS NULL OR montant_mga >= 0'],
            ['depenses', 'montant_audite', 'montant_audite IS NULL OR montant_audite >= 0'],
            ['engagements', 'montant', 'montant >= 0'],
            ['engagements', 'montant_mga', 'montant_mga IS NULL OR montant_mga >= 0'],
            ['decaissement_plans', 'montant_prevu', 'montant_prevu >= 0'],
            ['decaissement_plans', 'montant_mga', 'montant_mga IS NULL OR montant_mga >= 0'],
            ['decaissements', 'montant', 'montant >= 0'],
            ['decaissements', 'montant_mga', 'montant_mga IS NULL OR montant_mga >= 0'],
            ['composantes', 'budget', 'budget IS NULL OR budget >= 0'],
            ['activites', 'budget', 'budget IS NULL OR budget >= 0'],
            ['financement_contributions', 'montant', 'montant >= 0'],
            ['financement_contributions', 'montant_mga', 'montant_mga >= 0'],
            ['budget_pledges', 'montant', 'montant >= 0'],
            ['budget_pledges', 'montant_mga', 'montant_mga >= 0'],
            ['budget_approbations', 'montant_approuve', 'montant_approuve >= 0'],
            ['budget_approbations', 'montant_mga', 'montant_mga >= 0'],
            ['project_ideas', 'budget_total_estime', 'budget_total_estime IS NULL OR budget_total_estime >= 0'],
            ['project_ideas', 'contribution_nationale', 'contribution_nationale IS NULL OR contribution_nationale >= 0'],
            ['project_ideas', 'contribution_partenaires', 'contribution_partenaires IS NULL OR contribution_partenaires >= 0'],
            ['project_ideas', 'cofinancement_prive', 'cofinancement_prive IS NULL OR cofinancement_prive >= 0'],
            ['project_ideas', 'autres_financements', 'autres_financements IS NULL OR autres_financements >= 0'],
            ['project_idea_financements', 'montant_demande', 'montant_demande IS NULL OR montant_demande >= 0'],
            ['stakeholders', 'montant_estimatif', 'montant_estimatif IS NULL OR montant_estimatif >= 0'],
            // Indicateurs : les valeurs mesurées et la cible ne peuvent pas être
            // négatives ; taux_atteinte non plus (dérivé de deux valeurs >= 0).
            // 'ecart' (réalisée - cible) est volontairement EXCLU : il peut être
            // négatif (sous-performance), c'est une information utile à conserver.
            ['indicateurs', 'valeur_cible', 'valeur_cible >= 0'],
            ['indicateurs', 'valeur_realisee', 'valeur_realisee >= 0'],
            ['indicateurs', 'taux_atteinte', 'taux_atteinte >= 0'],
        ];
    }

    private function latLngChecks(): array
    {
        return [
            ['regions', 'latitude', '(latitude IS NULL) OR (latitude BETWEEN -90 AND 90)'],
            ['regions', 'longitude', '(longitude IS NULL) OR (longitude BETWEEN -180 AND 180)'],
            ['projects', 'latitude', '(latitude IS NULL) OR (latitude BETWEEN -90 AND 90)'],
            ['projects', 'longitude', '(longitude IS NULL) OR (longitude BETWEEN -180 AND 180)'],
            ['project_ideas', 'latitude', '(latitude IS NULL) OR (latitude BETWEEN -90 AND 90)'],
            ['project_ideas', 'longitude', '(longitude IS NULL) OR (longitude BETWEEN -180 AND 180)'],
            ['project_zone_points', 'latitude', 'latitude BETWEEN -90 AND 90'],
            ['project_zone_points', 'longitude', 'longitude BETWEEN -180 AND 180'],
        ];
    }

    public function up(): void
    {
        foreach (array_merge($this->positiveAmountChecks(), $this->latLngChecks()) as [$table, $column, $expr]) {
            $constraint = "chk_{$table}_{$column}";
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$expr})");
        }

        // Pourcentages bornés à [0, 100] (au-delà de la protection unsignedTinyInteger,
        // qui autorise jusqu'à 255).
        DB::statement("ALTER TABLE activites ADD CONSTRAINT chk_activites_pourcentage_avancement CHECK (pourcentage_avancement <= 100)");
    }

    public function down(): void
    {
        $constraints = array_map(
            fn ($row) => "chk_{$row[0]}_{$row[1]}",
            array_merge($this->positiveAmountChecks(), $this->latLngChecks())
        );
        $constraints[] = 'chk_activites_pourcentage_avancement';

        // Regrouper par table pour générer un DROP CONSTRAINT par table
        $byTable = [];
        foreach (array_merge($this->positiveAmountChecks(), $this->latLngChecks()) as [$table, $column]) {
            $byTable[$table][] = "chk_{$table}_{$column}";
        }
        $byTable['activites'][] = 'chk_activites_pourcentage_avancement';

        foreach ($byTable as $table => $names) {
            foreach (array_unique($names) as $name) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
            }
        }
    }
};

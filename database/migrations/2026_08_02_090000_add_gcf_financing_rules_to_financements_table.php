<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aligne le module Financement sur les règles de gestion financière GCF :
     *
     * - type_financement : origine du financement (GCF / co-financement public / privé).
     * - mode_contribution : forme de la contribution (numéraire / en nature).
     *   Un financement GCF est TOUJOURS en numéraire (règle imposée aussi côté
     *   FinancementController, pas seulement côté frontend).
     *
     * Choix de conception : les colonnes existantes budget_approuve / devise /
     * montant_mga / date_approbation sont réutilisées pour les DEUX modes de
     * contribution (relabellisées "Valeur estimée" / "Date de mise à
     * disposition" côté frontend quand mode_contribution = nature). Cela évite
     * de dupliquer le schéma et garde les agrégats/KPIs existants
     * (FinancementController::totaux / byProject) valables sans modification
     * pour les contributions en nature.
     */
    public function up(): void
    {
        Schema::table('financements', function (Blueprint $table) {
            $table->enum('type_financement', ['gcf', 'cofinancement_public', 'cofinancement_prive'])
                ->default('gcf')->after('project_id');
            $table->enum('mode_contribution', ['numeraire', 'nature'])
                ->default('numeraire')->after('type_financement');

            // Champs communs (numéraire ET nature)
            $table->text('description')->nullable()->after('statut');

            // Champs spécifiques à la contribution "en nature"
            $table->foreignId('categorie_contribution_id')->nullable()->after('description')
                ->constrained('contribution_categories')->nullOnDelete();

            // source_financement reste requis dans les deux modes (il identifie le
            // contributeur, que la contribution soit en numéraire ou en nature) ;
            // aucun changement de nullabilité nécessaire ici.
        });
    }

    public function down(): void
    {
        Schema::table('financements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categorie_contribution_id');
            // Correction (audit BDD) : cette méthode down() tentait de
            // supprimer 'organisme_contributeur' et 'observations', deux
            // colonnes qui n'ont jamais été créées par le up() ci-dessus
            // (probablement copié-collé d'une autre migration) — un
            // rollback aurait échoué avec une erreur PostgreSQL "column
            // does not exist". Seules les colonnes réellement ajoutées par
            // up() sont supprimées ici.
            $table->dropColumn(['type_financement', 'mode_contribution', 'description']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 3 de l'audit BDD (§2 — Tables isolées / mortes).
 *
 * Conformément à la règle "ne pas modifier une migration historique
 * directement" : les migrations de création d'origine
 * (2026_04_19_085555_create_rapports_table, 2026_04_19_085623_create_stats_table,
 * 2026_04_19_085937_create_auths_table, 2026_07_22_085033_create_activite_piece_jointes_table)
 * restent intactes ; cette nouvelle migration additive supprime les tables
 * qu'elles ont créées, une fois leur absence d'usage confirmée par une
 * recherche exhaustive dans le code (aucun contrôleur, aucune Resource,
 * aucun service ne référence les modèles Rapport, Stats, Auth, ni la table
 * activite_piece_jointes — cf. rapport d'audit §2) :
 *
 *  - rapports  : coquille vide (id + timestamps). Le module "rapports"
 *    réellement utilisé est `rapports_nationaux` (RapportNationalController)
 *    ; RapportController::byProject/show interrogent directement `projects`,
 *    jamais `rapports`.
 *  - stats     : coquille vide. StatsController calcule tous ses résultats
 *    par agrégation à la volée sur les tables métier, jamais depuis `stats`.
 *  - auths     : coquille vide. L'authentification réelle passe par `users`
 *    + Laravel Sanctum (AuthController), jamais par cette table.
 *  - activite_piece_jointes (variante orthographique erronée, créée par
 *    erreur 16 min après la vraie table `activite_pieces_jointes` le même
 *    jour) : le modèle ActivitePieceJointe pointe explicitement vers
 *    `activite_pieces_jointes` (avec commentaire expliquant pourquoi), donc
 *    `activite_piece_jointes` (sans le 's' à "piece") n'a jamais été écrite
 *    ni lue par le code applicatif.
 *
 * ⚠️ DESTRUCTIF si l'une de ces tables contient des données insérées
 * manuellement en dehors de l'application (accès direct psql, script
 * ponctuel...). Avant d'exécuter cette migration sur un environnement réel,
 * vérifier :
 *   SELECT count(*) FROM rapports; SELECT count(*) FROM stats;
 *   SELECT count(*) FROM auths; SELECT count(*) FROM activite_piece_jointes;
 * Si l'une de ces requêtes retourne > 0, NE PAS exécuter cette migration
 * sans avoir d'abord identifié l'origine de ces données.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rapports');
        Schema::dropIfExists('stats');
        Schema::dropIfExists('auths');
        Schema::dropIfExists('activite_piece_jointes');
    }

    /**
     * Recrée les 4 tables dans leur état d'origine (coquilles vides), pour
     * rester symétrique avec les migrations historiques en cas de rollback.
     */
    public function down(): void
    {
        Schema::create('rapports', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('stats', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('auths', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('activite_piece_jointes', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};

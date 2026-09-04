<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 6 de l'audit BDD (§É-3 — suivi pluriannuel des indicateurs).
 *
 * Jusqu'ici, `indicateurs` ne portait qu'une seule paire
 * valeur_cible/valeur_realisee avec une seule date_reference : suivre un
 * même indicateur sur plusieurs années obligeait à dupliquer nom/unite/
 * categorie/project_id à chaque nouvelle mesure. Cette migration est
 * ADDITIVE et NON DESTRUCTIVE : `indicateurs.valeur_realisee`,
 * `.date_reference`, `.taux_atteinte`, `.ecart`, `.niveau_performance`
 * restent en place et continuent de représenter la "mesure courante" de
 * l'indicateur (aucun consommateur existant — IndicateurController,
 * IndicateurResource, frontend — n'est cassé par ce changement).
 *
 * `indicateur_mesures` ajoute la capacité de conserver l'historique complet
 * des mesures dans le temps, sans devoir dupliquer la définition de
 * l'indicateur à chaque période :
 *
 *   indicateurs (définition stable : project_id, nom, unite, valeur_cible)
 *       └── indicateur_mesures (valeur_realisee, date_mesure, commentaire)
 *
 * La bascule complète du frontend vers ce modèle de série temporelle (par
 * opposition à la mesure "courante" unique) reste une décision produit à
 * planifier séparément — cette migration ne fait que rendre la structure
 * disponible, conformément à la consigne de ne pas sur-architecturer sans
 * besoin confirmé (cahier des charges §32).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicateur_mesures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicateur_id')->constrained('indicateurs')->cascadeOnDelete();
            $table->decimal('valeur_realisee', 15, 4);
            $table->date('date_mesure');
            $table->text('commentaire')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['indicateur_id', 'date_mesure']);
            // Une seule mesure par indicateur et par date : évite les doublons
            // de saisie pour une même période.
            $table->unique(['indicateur_id', 'date_mesure']);
        });

        // Contrainte CHECK cohérente avec celle déjà posée sur
        // indicateurs.valeur_realisee (migration 2026_08_24_080000).
        \Illuminate\Support\Facades\DB::statement(
            'ALTER TABLE indicateur_mesures ADD CONSTRAINT chk_indicateur_mesures_valeur_realisee CHECK (valeur_realisee >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('indicateur_mesures');
    }
};

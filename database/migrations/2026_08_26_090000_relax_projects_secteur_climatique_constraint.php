<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La colonne "secteur_climatique" de "projects" n'est plus la source de
 * vérité depuis l'introduction de la relation many-to-many
 * Project::domainesIntervention() (table de référence domaine_interventions,
 * migration 2026_06_24_204035_create_reference_tables_table). Elle sert
 * uniquement de miroir "legacy" en lecture seule, réécrit automatiquement
 * par Project::syncLegacyFields() avec le libellé complet du premier
 * domaine d'intervention lié.
 *
 * Elle avait cependant conservé son ancienne contrainte CHECK (héritée d'un
 * $table->enum() avec 9 slugs courts : adaptation, attenuation, resilience,
 * biodiversite, eau, foret, energie, transport, agriculture) — un
 * vocabulaire différent de celui, en texte libre, de la table
 * domaine_interventions ("Agriculture climato-intelligente", "Gestion des
 * ressources en eau", ...). Résultat : dès qu'un projet est lié à un
 * domaine d'intervention dont le libellé ne correspond à aucun des 9
 * anciens slugs, la moindre mise à jour du projet échoue avec
 * "SQLSTATE[23514] ... projects_secteur_climatique_check", même quand ce
 * champ n'est pas celui qu'on modifie intentionnellement.
 *
 * On supprime cette contrainte devenue obsolète et incompatible avec le
 * nouveau système de référence ; la colonne reste une simple chaîne libre
 * nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE projects DROP CONSTRAINT IF EXISTS projects_secteur_climatique_check');
    }

    public function down(): void
    {
        // Non réversible à l'identique : les données actuelles (libellés
        // complets de domaine_interventions) ne respectent plus l'ancien
        // vocabulaire à 9 slugs, donc recréer la contrainte casserait
        // aussitôt les lignes existantes. On ne la restaure pas.
    }
};

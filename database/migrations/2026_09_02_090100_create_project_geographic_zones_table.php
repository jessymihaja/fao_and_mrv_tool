<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "1 projet → plusieurs zones géographiques" (région, district et/ou
 * commune), affichées ensemble sur une seule carte.
 *
 * Choix d'architecture : on NE crée PAS de nouvelle table générique
 * "geographical_zones" — les tables regions/districts/communes existent
 * déjà avec leurs données de référence (voir 2026_04_19_085518_create_geos_table.php)
 * et sont réutilisées telles quelles. project_geographic_zones est une
 * relation polymorphe (zoneable_type/zoneable_id) vers Region, District ou
 * Commune — voir App\Models\ProjectGeographicZone.
 *
 * Ne touche à aucune colonne existante de `projects` (province_id,
 * region_id, district_id, commune_id, fokontany_id, zone_description
 * restent en place pour la sélection unique utilisée à la création du
 * projet — voir GeoZoneSelector.tsx dans le wizard) et ne touche pas non
 * plus à project_zone_points (polygone dessiné à la main, fonctionnalité
 * distincte et conservée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_geographic_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // Relation polymorphe manuelle vers Region|District|Commune.
            // zoneable_type stocke un alias court ('region'|'district'|'commune'),
            // pas le nom de classe complet — voir le morphMap enregistré
            // dans AppServiceProvider::boot().
            $table->string('zoneable_type', 20);
            $table->unsignedBigInteger('zoneable_id');

            $table->timestamps();

            // Empêche d'associer deux fois la même zone au même projet
            // (règle métier §8/§15 — "impossible d'associer deux fois la
            // même zone au même projet").
            $table->unique(['project_id', 'zoneable_type', 'zoneable_id'], 'project_zone_unique');
            $table->index(['zoneable_type', 'zoneable_id']);
        });

        // ── Migration des données existantes (§17 : aucune donnée perdue) ──
        // Chaque projet qui avait déjà une zone unique (via commune_id,
        // district_id ou region_id — du niveau le plus précis au moins
        // précis) devient une première zone dans le nouveau système
        // multi-zones. On ne migre PAS province_id/fokontany_id : ce ne
        // sont pas des niveaux gérés par le multi-zones (cf. décision
        // produit), mais ces colonnes restent intactes sur `projects`.
        $projects = DB::table('projects')
            ->select('id', 'commune_id', 'district_id', 'region_id')
            ->whereNotNull('commune_id')
            ->orWhereNotNull('district_id')
            ->orWhereNotNull('region_id')
            ->get();

        $now  = now();
        $rows = [];

        foreach ($projects as $project) {
            if ($project->commune_id) {
                $rows[] = [
                    'project_id'    => $project->id,
                    'zoneable_type' => 'commune',
                    'zoneable_id'   => $project->commune_id,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            } elseif ($project->district_id) {
                $rows[] = [
                    'project_id'    => $project->id,
                    'zoneable_type' => 'district',
                    'zoneable_id'   => $project->district_id,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            } elseif ($project->region_id) {
                $rows[] = [
                    'project_id'    => $project->id,
                    'zoneable_type' => 'region',
                    'zoneable_id'   => $project->region_id,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        // Insertion par lots de 500 pour éviter une requête géante sur les
        // gros jeux de données.
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('project_geographic_zones')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_geographic_zones');
    }
};

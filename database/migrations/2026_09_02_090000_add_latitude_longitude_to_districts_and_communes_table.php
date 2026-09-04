<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute latitude/longitude (nullable) sur districts et communes, sur le
 * même modèle que regions.latitude/longitude déjà existant.
 *
 * Pourquoi : le module "Zones géographiques multiples" (project_geographic_zones)
 * doit pouvoir afficher un marqueur pour CHAQUE zone associée à un projet
 * (région, district ou commune) sur la carte Leaflet. Aucune géométrie
 * réelle (GeoJSON) n'existe en base pour district/commune — ces colonnes
 * permettent d'afficher un marqueur au centroïde quand la coordonnée est
 * renseignée, avec repli sur le centroïde de la région parente sinon
 * (voir App\Models\District::displayLatitude()/displayLongitude() et
 * App\Models\Commune équivalents).
 *
 * Ne supprime ni ne modifie aucune colonne existante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('region_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });

        Schema::table('communes', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('district_id');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('communes', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};

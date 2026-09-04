<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('indicateur_referentiels', function (Blueprint $table) {
            $table->id();
            $table->enum('dimension', ['financier', 'physique', 'adaptation', 'attenuation']);
            $table->string('nom', 200);
            $table->string('unite', 50);
            $table->string('frequence', 50)->nullable();
            $table->timestamps();

            $table->unique(['dimension', 'nom']);
        });

        // Seed avec le référentiel qui était codé en dur côté front,
        // pour ne rien perdre lors de la migration.
        $now = now();
        DB::table('indicateur_referentiels')->insert([
            ['dimension' => 'financier',   'nom' => 'Montant approuvé',                                    'unite' => 'USD',        'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'financier',   'nom' => 'Montant engagé',                                      'unite' => 'Ar',         'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'financier',   'nom' => 'Montant décaissé',                                    'unite' => 'Ar',         'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'financier',   'nom' => "Taux d'exécution financière",                         'unite' => '%',          'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'financier',   'nom' => 'Co-financement mobilisé',                              'unite' => 'USD',        'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'financier',   'nom' => 'Répartition sectorielle des investissements',          'unite' => '%',          'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'physique',    'nom' => 'Nombre de bénéficiaires',                              'unite' => 'personnes',  'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'physique',    'nom' => "Nombre d'infrastructures réalisées",                   'unite' => 'unités',     'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'physique',    'nom' => 'Surface restaurée ou protégée',                        'unite' => 'ha',         'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'physique',    'nom' => 'Nombre de ménages résilients',                         'unite' => 'ménages',    'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'physique',    'nom' => 'Nombre de formations réalisées',                       'unite' => 'sessions',   'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'adaptation',  'nom' => "Population bénéficiant d'une meilleure résilience",    'unite' => 'personnes',  'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'adaptation',  'nom' => 'Réduction de la vulnérabilité',                        'unite' => '%',          'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'adaptation',  'nom' => "Systèmes d'alerte précoce installés",                  'unite' => 'systèmes',   'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'attenuation', 'nom' => 'Tonnes de CO₂ évitées',                                'unite' => 'tCO₂eq',     'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'attenuation', 'nom' => 'Énergie renouvelable produite',                        'unite' => 'MWh',        'created_at' => $now, 'updated_at' => $now],
            ['dimension' => 'attenuation', 'nom' => 'Réduction de la consommation énergétique',             'unite' => '%',          'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('indicateur_referentiels');
    }
};

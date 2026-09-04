<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Seeder;

/**
 * Crée les 6 provinces et les régions de Madagascar avec leurs coordonnées.
 *
 * Contrairement à ce qu'on pensait initialement, la table `regions` était
 * totalement VIDE (pas juste latitude/longitude à NULL) — d'où l'échec
 * silencieux du précédent seeder (UPDATE sur une table vide = 0 ligne
 * affectée, sans erreur).
 *
 * Remplace RegionCoordinatesSeeder.php — utilisez celui-ci à la place.
 */
class ProvincesAndRegionsSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Antananarivo' => [
                'Analamanga'     => [-18.8792, 47.5079],
                'Bongolava'      => [-18.7667, 46.0500],
                'Itasy'          => [-19.0167, 46.7667],
                'Vakinankaratra' => [-19.8667, 47.0333],
            ],
            'Fianarantsoa' => [
                "Amoron'i Mania"    => [-20.5333, 47.2500],
                'Atsimo-Atsinanana' => [-22.8167, 47.8333],
                'Fitovinany'        => [-22.1333, 48.0167],
                'Haute Matsiatra'   => [-21.4500, 47.0833],
                'Ihorombe'          => [-22.4000, 46.1167],
                'Vatovavy'          => [-21.2333, 48.3333],
            ],
            'Toamasina' => [
                'Alaotra-Mangoro' => [-17.8333, 48.4167],
                'Analanjirofo'    => [-17.3833, 49.4167],
                'Atsinanana'      => [-18.1500, 49.4167],
            ],
            'Mahajanga' => [
                'Betsiboka' => [-16.9500, 46.8333],
                'Boeny'     => [-15.7167, 46.3167],
                'Melaky'    => [-18.0667, 44.0167],
                'Sofia'     => [-14.8833, 47.9833],
            ],
            'Toliara' => [
                'Androy'           => [-25.1667, 46.0833],
                'Anosy'            => [-25.0333, 46.9833],
                'Atsimo-Andrefana' => [-23.3500, 43.6667],
                'Menabe'           => [-20.2833, 44.3167],
            ],
            'Antsiranana' => [
                'Diana' => [-12.2787, 49.2917],
                'Sava'  => [-14.2667, 50.1667],
            ],
        ];

        foreach ($data as $provinceName => $regions) {
            $province = Province::firstOrCreate(['nom' => $provinceName]);

            foreach ($regions as $regionName => [$lat, $lng]) {
                Region::updateOrCreate(
                    ['nom' => $regionName],
                    [
                        'province_id' => $province->id,
                        'latitude'    => $lat,
                        'longitude'   => $lng,
                    ]
                );
            }
        }

        $this->command->info('→ ' . Province::count() . ' provinces et ' . Region::count() . ' régions créées/mises à jour.');
    }
}


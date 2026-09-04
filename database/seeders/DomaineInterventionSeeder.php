<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DomaineInterventionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('domaine_interventions')->truncate();

        DB::table('domaine_interventions')->insert([
            [
                'id_domaine_intervention' => 1,
                'designation' => 'Eau et Assainissement',
                'description' => 'Gestion intégrée des ressources en eau, accès à l\'eau potable et résilience hydraulique.',
            ],
            [
                'id_domaine_intervention' => 2,
                'designation' => 'Zones Côtières et Écosystèmes',
                'description' => 'Protection des littoraux, érosion côtière et conservation de la biodiversité marine.',
            ],
            [
                'id_domaine_intervention' => 3,
                'designation' => 'Forêts',
                'description' => null,
            ],
            [
                'id_domaine_intervention' => 4,
                'designation' => 'Agriculture',
                'description' => null,
            ],
            [
                'id_domaine_intervention' => 5,
                'designation' => 'Energie',
                'description' => null,
            ],
        ]);
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassificationSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('classifications')->truncate();

        DB::table('classifications')->insert([
            [
                'id_classification' => 1,
                'designation' => 'Transversal (Cross-cutting)',
            ],
            [
                'id_classification' => 2,
                'designation' => 'Renforcement de capacités / Readiness',
            ],
            [
                'id_classification' => 3,
                'designation' => 'attenuation',
            ],
            [
                'id_classification' => 4,
                'designation' => 'Adaptation',
            ],
            [
                'id_classification' => 5,
                'designation' => 'Attenuation + Adaptation',
            ],
        ]);
    }
}
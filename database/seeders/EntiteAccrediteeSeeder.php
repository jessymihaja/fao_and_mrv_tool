<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EntiteAccrediteeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('entite_accreditees')->truncate();

        DB::table('entite_accreditees')->insert([
            [
                'id_entite_accreditee' => 1,
                'designation' => 'Banque Mondiale',
                'sigle' => 'BM',
            ],
            [
                'id_entite_accreditee' => 2,
                'designation' => 'WWf',
                'sigle' => 'WWF',
            ],
            [
                'id_entite_accreditee' => 3,
                'designation' => 'Conservation International',
                'sigle' => 'CI',
            ],
            [
                'id_entite_accreditee' => 4,
                'designation' => 'IFAD',
                'sigle' => 'IFAD',
            ],
            [
                'id_entite_accreditee' => 5,
                'designation' => 'GIZ',
                'sigle' => 'GIZ',
            ],
            [
                'id_entite_accreditee' => 6,
                'designation' => 'Camco Management Limited',
                'sigle' => 'CAMCO',
            ],
            [
                'id_entite_accreditee' => 7,
                'designation' => 'FMO',
                'sigle' => 'FMO',
            ],
            [
                'id_entite_accreditee' => 8,
                'designation' => 'Nederlandse Financierings-Maatschappij voor Ontwikkelingslanden',
                'sigle' => 'FMO',
            ],
            [
                'id_entite_accreditee' => 9,
                'designation' => 'Agence Française de Développement',
                'sigle' => 'AFD',
            ],
            [
                'id_entite_accreditee' => 10,
                'designation' => 'KFW',
                'sigle' => 'KFW',
            ],
        ]);
    }
}
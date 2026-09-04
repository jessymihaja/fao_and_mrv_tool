<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent (updateOrInsert) : les 3 devises de base sont déjà insérées
 * par la migration 2026_08_24_080400_create_currencies_table (nécessaire
 * pour que les contraintes FK ajoutées par la migration suivante ne
 * cassent pas sur un `migrate` sans `--seed`). Ce seeder existe pour
 * rester rejouable sans erreur sur une base déjà migrée, et comme point
 * d'entrée naturel pour ajouter une future devise sans écrire de
 * migration.
 */
class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'iso_code' => 'USD', 'designation' => 'Dollar américain', 'symbole' => '$',  'actif' => true],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $currency['code']],
                array_merge($currency, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 6 de l'audit BDD (§É-5 — évolutivité). La colonne `devise` était
 * déclarée en `enum('AR','USD','EUR')` séparément dans 10 tables
 * financières — exactement le cas de redondance visé par la section 7 du
 * cahier des charges ("éviter de multiplier les valeurs codées en dur dans
 * plusieurs endroits"). Ajouter une nouvelle devise nécessitait jusqu'ici
 * 10 migrations ALTER TYPE ; désormais une seule ligne dans `currencies`.
 *
 * Le code `AR` est conservé tel quel comme clé primaire (identique aux
 * valeurs déjà stockées dans les 10 tables — aucune conversion de données
 * nécessaire, aucun risque de migration destructive). Le code ISO 4217
 * officiel de l'Ariary malgache (`MGA`) est conservé à titre indicatif dans
 * la colonne `iso_code`, réconciliant la colonne `devise='AR'` avec les
 * colonnes `montant_mga` utilisées ailleurs dans le schéma pour désigner la
 * même monnaie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary(); // AR, USD, EUR — clé identique aux valeurs déjà stockées
            $table->string('iso_code', 3)->nullable(); // MGA pour 'AR' — voir docblock ci-dessus
            $table->string('designation');
            $table->string('symbole', 8)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        // Les 3 devises sont insérées ICI (et non uniquement via un seeder
        // séparé exécuté plus tard) car la migration suivante
        // (2026_08_24_080500_convert_devise_columns_to_fk) ajoute des
        // contraintes FK depuis 10 tables déjà peuplées vers ces codes : la
        // conversion échouerait sur `php artisan migrate` seul (sans
        // `--seed`) si ces lignes n'existaient pas déjà à cet instant.
        DB::table('currencies')->insert([
            ['code' => 'AR',  'iso_code' => 'MGA', 'designation' => 'Ariary malgache', 'symbole' => 'Ar', 'actif' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'USD', 'iso_code' => 'USD', 'designation' => 'Dollar américain', 'symbole' => '$', 'actif' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EUR', 'iso_code' => 'EUR', 'designation' => 'Euro', 'symbole' => '€', 'actif' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiels "Type de résultat", "Type de bénéficiaire" et "Catégorie de
 * bénéficiaire" — même schéma (id, designation) et même mécanisme d'ajout
 * en ligne ("+ Ajouter") que les autres référentiels de l'application
 * (Classification, Secteur, PerspectiveType...). Remplace les enums fixes
 * utilisés jusqu'ici pour permettre à l'utilisateur d'ajouter une valeur
 * manquante sans intervention développeur (voir migration suivante pour
 * la conversion des colonnes existantes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_types', function (Blueprint $table) {
            $table->id();
            $table->string('designation')->unique();
            $table->timestamps();
        });

        Schema::create('beneficiary_types', function (Blueprint $table) {
            $table->id();
            $table->string('designation')->unique();
            $table->timestamps();
        });

        Schema::create('beneficiary_categories', function (Blueprint $table) {
            $table->id();
            $table->string('designation')->unique();
            $table->timestamps();
        });

        $now = now();

        foreach (['Impact', 'Outcome / Effet', 'Output / Produit'] as $designation) {
            DB::table('result_types')->insert(['designation' => $designation, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (['Direct', 'Indirect'] as $designation) {
            DB::table('beneficiary_types')->insert(['designation' => $designation, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([
            'Agriculteurs', 'Pêcheurs', 'Ménages', 'Communautés locales', 'Femmes', 'Jeunes',
            'Entreprises', 'Organisations communautaires', 'Institutions publiques', 'ONG / OSC', 'Autre',
        ] as $designation) {
            DB::table('beneficiary_categories')->insert(['designation' => $designation, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiary_categories');
        Schema::dropIfExists('beneficiary_types');
        Schema::dropIfExists('result_types');
    }
};

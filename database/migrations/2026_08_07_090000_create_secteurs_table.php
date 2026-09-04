<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Référentiel "Secteurs" du module Idées de projet — indépendant des
     * référentiels "Classifications" et "Domaines d'intervention" déjà
     * utilisés par le module Projet (contenu différent : liste fermée de
     * secteurs simples demandée par le cahier des charges), mais construit
     * exactement sur le même schéma (id, designation) pour rester cohérent
     * avec le reste de l'application et permettre l'ajout en ligne
     * (SelectAvecAjout) au même titre que les autres référentiels.
     */
    public function up(): void
    {
        Schema::create('secteurs', function (Blueprint $table) {
            $table->id();
            $table->string('designation')->unique();
            $table->timestamps();
        });

        $secteurs = ['Agriculture', 'Forêt', 'Eau', 'Énergie', 'Transport', 'Déchets', 'Santé', 'Biodiversité', 'Adaptation', 'Atténuation', 'Autre'];
        foreach ($secteurs as $designation) {
            DB::table('secteurs')->insert([
                'designation' => $designation,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('secteurs');
    }
};

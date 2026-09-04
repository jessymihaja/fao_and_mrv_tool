<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Référentiel "Type" du module Perspectives des projets — même schéma
     * (id, designation) et même mécanisme d'ajout en ligne ("+") que les
     * autres référentiels de l'application (Secteur, Classification...).
     * Remplace l'enum fixe utilisé jusqu'ici (voir migration suivante).
     */
    public function up(): void
    {
        Schema::create('perspective_types', function (Blueprint $table) {
            $table->id();
            $table->string('designation')->unique();
            $table->timestamps();
        });

        $now = now();
        foreach (['Extension', 'Pérennisation', 'Nouveau financement', 'Autre'] as $designation) {
            DB::table('perspective_types')->insert([
                'designation' => $designation, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('perspective_types');
    }
};

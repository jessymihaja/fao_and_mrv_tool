<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace l'enum fixe results.type par une clé étrangère vers le nouveau
 * référentiel result_types, pour permettre l'ajout en ligne ("+ Ajouter")
 * de nouveaux types depuis le formulaire — même principe que la conversion
 * project_perspectives.type -> type_id. Les données existantes sont
 * automatiquement transférées (aucune perte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->foreignId('result_type_id')->nullable()->after('type')
                ->constrained('result_types')->restrictOnDelete();
        });

        $map = [
            'impact'  => 'Impact',
            'outcome' => 'Outcome / Effet',
            'output'  => 'Output / Produit',
        ];
        foreach ($map as $enumValue => $designation) {
            $typeId = DB::table('result_types')->where('designation', $designation)->value('id');
            if ($typeId) {
                DB::table('results')->where('type', $enumValue)->update(['result_type_id' => $typeId]);
            }
        }
        // Filet de sécurité : toute ligne non reconnue bascule sur "Output / Produit".
        $fallbackId = DB::table('result_types')->where('designation', 'Output / Produit')->value('id');
        if ($fallbackId) {
            DB::table('results')->whereNull('result_type_id')->update(['result_type_id' => $fallbackId]);
        }

        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->enum('type', ['impact', 'outcome', 'output'])->default('output')->after('indicateur_id');
        });

        $map = [
            'Impact'           => 'impact',
            'Outcome / Effet'  => 'outcome',
            'Output / Produit' => 'output',
        ];
        foreach ($map as $designation => $enumValue) {
            $typeId = DB::table('result_types')->where('designation', $designation)->value('id');
            if ($typeId) {
                DB::table('results')->where('result_type_id', $typeId)->update(['type' => $enumValue]);
            }
        }
        DB::table('results')->whereNull('type')->update(['type' => 'output']);

        Schema::table('results', function (Blueprint $table) {
            $table->dropForeign(['result_type_id']);
            $table->dropColumn('result_type_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace les enums fixes beneficiaries.type et beneficiaries.category par
 * des clés étrangères vers les référentiels beneficiary_types et
 * beneficiary_categories, pour permettre l'ajout en ligne ("+ Ajouter") de
 * nouvelles valeurs depuis le formulaire — même principe que pour
 * results.type. Les données existantes sont automatiquement transférées
 * (aucune perte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->foreignId('beneficiary_type_id')->nullable()->after('type')
                ->constrained('beneficiary_types')->restrictOnDelete();
            $table->foreignId('beneficiary_category_id')->nullable()->after('category')
                ->constrained('beneficiary_categories')->restrictOnDelete();
        });

        $typeMap = ['direct' => 'Direct', 'indirect' => 'Indirect'];
        foreach ($typeMap as $enumValue => $designation) {
            $id = DB::table('beneficiary_types')->where('designation', $designation)->value('id');
            if ($id) {
                DB::table('beneficiaries')->where('type', $enumValue)->update(['beneficiary_type_id' => $id]);
            }
        }
        $fallbackType = DB::table('beneficiary_types')->where('designation', 'Direct')->value('id');
        if ($fallbackType) {
            DB::table('beneficiaries')->whereNull('beneficiary_type_id')->update(['beneficiary_type_id' => $fallbackType]);
        }

        $categoryMap = [
            'agriculteurs'                  => 'Agriculteurs',
            'pecheurs'                      => 'Pêcheurs',
            'menages'                       => 'Ménages',
            'communautes_locales'           => 'Communautés locales',
            'femmes'                        => 'Femmes',
            'jeunes'                        => 'Jeunes',
            'entreprises'                   => 'Entreprises',
            'organisations_communautaires'  => 'Organisations communautaires',
            'institutions_publiques'        => 'Institutions publiques',
            'ong_osc'                       => 'ONG / OSC',
            'autre'                         => 'Autre',
        ];
        foreach ($categoryMap as $enumValue => $designation) {
            $id = DB::table('beneficiary_categories')->where('designation', $designation)->value('id');
            if ($id) {
                DB::table('beneficiaries')->where('category', $enumValue)->update(['beneficiary_category_id' => $id]);
            }
        }
        $fallbackCategory = DB::table('beneficiary_categories')->where('designation', 'Autre')->value('id');
        if ($fallbackCategory) {
            DB::table('beneficiaries')->whereNull('beneficiary_category_id')->update(['beneficiary_category_id' => $fallbackCategory]);
        }

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropColumn(['type', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->enum('type', ['direct', 'indirect'])->default('direct')->after('project_id');
            $table->enum('category', [
                'agriculteurs', 'pecheurs', 'menages', 'communautes_locales',
                'femmes', 'jeunes', 'entreprises', 'organisations_communautaires',
                'institutions_publiques', 'ong_osc', 'autre',
            ])->default('autre')->after('type');
        });

        $typeMap = ['Direct' => 'direct', 'Indirect' => 'indirect'];
        foreach ($typeMap as $designation => $enumValue) {
            $id = DB::table('beneficiary_types')->where('designation', $designation)->value('id');
            if ($id) {
                DB::table('beneficiaries')->where('beneficiary_type_id', $id)->update(['type' => $enumValue]);
            }
        }

        $categoryMap = [
            'Agriculteurs' => 'agriculteurs', 'Pêcheurs' => 'pecheurs', 'Ménages' => 'menages',
            'Communautés locales' => 'communautes_locales', 'Femmes' => 'femmes', 'Jeunes' => 'jeunes',
            'Entreprises' => 'entreprises', 'Organisations communautaires' => 'organisations_communautaires',
            'Institutions publiques' => 'institutions_publiques', 'ONG / OSC' => 'ong_osc',
        ];
        foreach ($categoryMap as $designation => $enumValue) {
            $id = DB::table('beneficiary_categories')->where('designation', $designation)->value('id');
            if ($id) {
                DB::table('beneficiaries')->where('beneficiary_category_id', $id)->update(['category' => $enumValue]);
            }
        }
        DB::table('beneficiaries')->whereNull('category')->update(['category' => 'autre']);

        Schema::table('beneficiaries', function (Blueprint $table) {
            $table->dropForeign(['beneficiary_type_id']);
            $table->dropForeign(['beneficiary_category_id']);
            $table->dropColumn(['beneficiary_type_id', 'beneficiary_category_id']);
        });
    }
};

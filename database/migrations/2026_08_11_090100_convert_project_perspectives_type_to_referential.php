<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remplace l'enum fixe project_perspectives.type par une clé étrangère
     * vers le nouveau référentiel perspective_types, pour permettre l'ajout
     * en ligne ("+ Ajouter") de nouveaux types depuis le formulaire — comme
     * demandé, sur le même principe que les autres référentiels de
     * l'application. Les données existantes sont automatiquement
     * transférées (aucune perte).
     *
     * type_id reste nullable au niveau base (pas de ->change() ici :
     * doctrine/dbal n'est pas installé sur ce projet) — le champ est rendu
     * obligatoire via la validation Laravel dans le contrôleur, comme pour
     * les champs équivalents des autres modules (ex. categorie_id sur
     * Stakeholder).
     */
    public function up(): void
    {
        Schema::table('project_perspectives', function (Blueprint $table) {
            $table->foreignId('type_id')->nullable()->after('type')
                ->constrained('perspective_types')->restrictOnDelete();
        });

        $map = [
            'extension'           => 'Extension',
            'perennisation'       => 'Pérennisation',
            'nouveau_financement' => 'Nouveau financement',
            'autre'               => 'Autre',
        ];
        foreach ($map as $enumValue => $designation) {
            $typeId = DB::table('perspective_types')->where('designation', $designation)->value('id');
            if ($typeId) {
                DB::table('project_perspectives')->where('type', $enumValue)->update(['type_id' => $typeId]);
            }
        }
        // Filet de sécurité : toute ligne non reconnue bascule sur "Autre".
        $autreId = DB::table('perspective_types')->where('designation', 'Autre')->value('id');
        if ($autreId) {
            DB::table('project_perspectives')->whereNull('type_id')->update(['type_id' => $autreId]);
        }

        Schema::table('project_perspectives', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('project_perspectives', function (Blueprint $table) {
            $table->enum('type', ['extension', 'perennisation', 'nouveau_financement', 'autre'])
                ->default('autre')->after('project_id');
        });

        $map = [
            'Extension'           => 'extension',
            'Pérennisation'       => 'perennisation',
            'Nouveau financement' => 'nouveau_financement',
        ];
        foreach ($map as $designation => $enumValue) {
            $typeId = DB::table('perspective_types')->where('designation', $designation)->value('id');
            if ($typeId) {
                DB::table('project_perspectives')->where('type_id', $typeId)->update(['type' => $enumValue]);
            }
        }
        DB::table('project_perspectives')->whereNull('type')->update(['type' => 'autre']);

        Schema::table('project_perspectives', function (Blueprint $table) {
            $table->dropForeign(['type_id']);
            $table->dropColumn('type_id');
        });
    }
};

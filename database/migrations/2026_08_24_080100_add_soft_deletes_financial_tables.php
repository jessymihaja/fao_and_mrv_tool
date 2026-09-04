<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 2 de l'audit BDD (§10 Soft deletes) — financements et documents sont
 * des données financières/juridiques historiques dont la suppression
 * définitive et immédiate n'est pas souhaitable.
 *
 * Correction apportée pendant la mise en œuvre de cette migration :
 * `budget_pledges` et `budget_approbations` avaient été identifiés à tort
 * dans le rapport d'audit initial comme dépourvus de soft delete (le
 * modèle Eloquent déclare bien `use SoftDeletes`, ce qui avait suggéré la
 * même conclusion pour la migration) ; une relecture directe de leurs
 * migrations de création confirme qu'elles ont déjà `$table->softDeletes()`
 * depuis l'origine. Cette migration ne touche donc QUE `financements` et
 * `documents`, qui sont les deux seules tables réellement concernées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financements', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('financements', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('documents', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};

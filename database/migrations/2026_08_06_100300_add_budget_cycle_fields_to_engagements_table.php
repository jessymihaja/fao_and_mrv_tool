<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module de suivi du cycle de vie des budgets climatiques — étape 3 :
     * "Budgets engagés" (Committed).
     *
     * La table `engagements` existait déjà (accord juridique signé) et est
     * déjà utilisée par l'onglet "Suivi des financements" — on l'enrichit
     * avec les champs manquants du cahier des charges (référence de
     * l'accord, bailleur, devise, équivalent MGA, justificatif, portée
     * composante/activité) sans casser l'écran existant.
     */
    public function up(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->foreignId('composante_id')->nullable()->after('financement_id')
                ->constrained('composantes')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->after('composante_id')
                ->constrained('activites')->nullOnDelete();

            $table->string('reference_accord')->nullable()->after('date');
            $table->foreignId('bailleur_id')->nullable()->after('reference_accord')
                ->constrained('organismes_contributeurs')->nullOnDelete();

            $table->enum('devise', ['AR', 'USD', 'EUR'])->default('AR')->after('montant');
            $table->decimal('montant_mga', 18, 2)->nullable()->after('devise');

            $table->string('justificatif_path')->nullable()->after('description');
            $table->string('justificatif_name')->nullable()->after('justificatif_path');
        });

        // Rétrocompatibilité : les accords déjà saisis étaient implicitement
        // en Ariary (aucune devise gérée jusqu'ici) — on aligne montant_mga
        // sur montant pour que les totaux consolidés restent corrects.
        DB::table('engagements')->whereNull('montant_mga')->update(['montant_mga' => DB::raw('montant')]);
    }

    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            $table->dropForeign(['composante_id']);
            $table->dropForeign(['activite_id']);
            $table->dropForeign(['bailleur_id']);
            $table->dropColumn([
                'composante_id', 'activite_id', 'reference_accord', 'bailleur_id',
                'devise', 'montant_mga', 'justificatif_path', 'justificatif_name',
            ]);
        });
    }
};

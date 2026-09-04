<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module de suivi du cycle de vie des budgets climatiques — étape 6 :
     * "Budgets décaissés" (Disbursed).
     *
     * La table `decaissements` existait déjà (fonds réellement transférés)
     * et est déjà utilisée par l'onglet "Suivi des financements" — on
     * l'enrichit avec les champs manquants (devise, équivalent MGA,
     * bénéficiaire, commentaire, justificatif, portée composante/activité)
     * sans casser l'écran existant.
     */
    public function up(): void
    {
        Schema::table('decaissements', function (Blueprint $table) {
            $table->foreignId('composante_id')->nullable()->after('financement_id')
                ->constrained('composantes')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->after('composante_id')
                ->constrained('activites')->nullOnDelete();

            $table->enum('devise', ['AR', 'USD', 'EUR'])->default('AR')->after('montant');
            $table->decimal('montant_mga', 18, 2)->nullable()->after('devise');

            $table->string('beneficiaire')->nullable()->after('reference');
            $table->text('commentaire')->nullable()->after('beneficiaire');
            $table->string('justificatif_path')->nullable()->after('commentaire');
            $table->string('justificatif_name')->nullable()->after('justificatif_path');
        });

        DB::table('decaissements')->whereNull('montant_mga')->update(['montant_mga' => DB::raw('montant')]);
    }

    public function down(): void
    {
        Schema::table('decaissements', function (Blueprint $table) {
            $table->dropForeign(['composante_id']);
            $table->dropForeign(['activite_id']);
            $table->dropColumn([
                'composante_id', 'activite_id', 'devise', 'montant_mga',
                'beneficiaire', 'commentaire', 'justificatif_path', 'justificatif_name',
            ]);
        });
    }
};

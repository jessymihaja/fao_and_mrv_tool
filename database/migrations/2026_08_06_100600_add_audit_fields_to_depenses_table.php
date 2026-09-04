<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module de suivi du cycle de vie des budgets climatiques — étape 7 :
     * "Budgets audités / dépensés" (Audited/Spent).
     *
     * La table `depenses` existante porte déjà la dépense elle-même
     * (montant, date, bénéficiaire, justification) — on ajoute ici le volet
     * "audit" du cahier des charges (montant audité, organisme d'audit,
     * rapport d'audit, observation) comme un second temps optionnel :
     * une dépense est d'abord enregistrée (`statut = depense`), puis
     * peut être auditée plus tard (`statut = audite`) sans dupliquer
     * l'enregistrement.
     */
    public function up(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->foreignId('composante_id')->nullable()->after('financement_id')
                ->constrained('composantes')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->after('composante_id')
                ->constrained('activites')->nullOnDelete();

            $table->enum('devise', ['AR', 'USD', 'EUR'])->default('AR')->after('montant');
            $table->decimal('montant_mga', 18, 2)->nullable()->after('devise');

            $table->decimal('montant_audite', 18, 2)->nullable()->after('montant_mga');
            $table->string('organisme_audit')->nullable()->after('montant_audite');
            $table->date('date_audit')->nullable()->after('organisme_audit');
            $table->string('rapport_audit_path')->nullable()->after('date_audit');
            $table->string('rapport_audit_name')->nullable()->after('rapport_audit_path');
            $table->text('observation_audit')->nullable()->after('rapport_audit_name');

            $table->enum('statut', ['depense', 'audite'])->default('depense')->after('observation_audit');
        });

        DB::table('depenses')->whereNull('montant_mga')->update(['montant_mga' => DB::raw('montant')]);
    }

    public function down(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->dropForeign(['composante_id']);
            $table->dropForeign(['activite_id']);
            $table->dropColumn([
                'composante_id', 'activite_id', 'devise', 'montant_mga',
                'montant_audite', 'organisme_audit', 'date_audit',
                'rapport_audit_path', 'rapport_audit_name', 'observation_audit', 'statut',
            ]);
        });
    }
};

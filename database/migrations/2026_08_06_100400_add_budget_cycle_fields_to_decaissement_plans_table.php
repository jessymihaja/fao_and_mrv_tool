<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module de suivi du cycle de vie des budgets climatiques — étape 5 :
     * "Budgets programmés / planifiés".
     *
     * La table `decaissement_plans` existait déjà côté backend (route +
     * contrôleur) mais n'était rattachée à aucun écran du frontend — on la
     * réactive ici comme support de l'étape "Programmé" du cycle plutôt que
     * de créer une table de plus, et on ajoute les champs du cahier des
     * charges (exercice budgétaire, année, description, justificatif,
     * devise/équivalent MGA, portée composante/activité).
     */
    public function up(): void
    {
        Schema::table('decaissement_plans', function (Blueprint $table) {
            $table->foreignId('composante_id')->nullable()->after('financement_id')
                ->constrained('composantes')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->after('composante_id')
                ->constrained('activites')->nullOnDelete();

            $table->string('exercice_budgetaire')->nullable()->after('date_prevue');
            $table->unsignedSmallInteger('annee')->nullable()->after('exercice_budgetaire');

            $table->enum('devise', ['AR', 'USD', 'EUR'])->default('AR')->after('montant_prevu');
            $table->decimal('montant_mga', 18, 2)->nullable()->after('devise');

            $table->text('description')->nullable()->after('statut');
            $table->string('justificatif_path')->nullable()->after('description');
            $table->string('justificatif_name')->nullable()->after('justificatif_path');
        });

        DB::table('decaissement_plans')->whereNull('montant_mga')->update(['montant_mga' => DB::raw('montant_prevu')]);
    }

    public function down(): void
    {
        Schema::table('decaissement_plans', function (Blueprint $table) {
            $table->dropForeign(['composante_id']);
            $table->dropForeign(['activite_id']);
            $table->dropColumn([
                'composante_id', 'activite_id', 'exercice_budgetaire', 'annee',
                'devise', 'montant_mga', 'description', 'justificatif_path', 'justificatif_name',
            ]);
        });
    }
};

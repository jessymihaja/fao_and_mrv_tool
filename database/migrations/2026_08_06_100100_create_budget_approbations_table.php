<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module de suivi du cycle de vie des budgets climatiques — étape 4 :
     * "Budgets approuvés" (Approved).
     *
     * Le Financement porte déjà un couple (budget_approuve, date_approbation)
     * qui reste inchangé — c'est le montant "de référence" affiché partout
     * ailleurs dans l'appli (Aperçu financier, totaux, etc.). Cette nouvelle
     * table ajoute l'historique complet et traçable des décisions
     * d'approbation (organe, référence, décision, pièce jointe), utile
     * quand un financement fait l'objet de plusieurs décisions/avenants
     * successifs au fil de sa vie.
     */
    public function up(): void
    {
        Schema::create('budget_approbations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financement_id')->constrained('financements')->cascadeOnDelete();
            $table->foreignId('composante_id')->nullable()->constrained('composantes')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->constrained('activites')->nullOnDelete();

            $table->date('date_approbation');
            $table->foreignId('organisme_id')->nullable()->constrained('organismes_contributeurs')->nullOnDelete();

            $table->decimal('montant_approuve', 18, 2);
            $table->enum('devise', ['AR', 'USD', 'EUR'])->default('AR');
            $table->decimal('montant_mga', 18, 2);

            $table->string('reference')->nullable();
            $table->text('decision')->nullable();

            $table->string('justificatif_path')->nullable();
            $table->string('justificatif_name')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_approbations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module de suivi du cycle de vie des budgets climatiques — étape 1 :
     * "Budgets annoncés / promis" (Pledges).
     *
     * Montants publiquement annoncés par un bailleur (COP, GCF, Banque
     * Mondiale...) sans engagement juridique. Toujours rattaché à un
     * Financement (traçabilité), et optionnellement à une Composante ou une
     * Activité si l'annonce ne concerne qu'une partie du projet.
     */
    public function up(): void
    {
        Schema::create('budget_pledges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financement_id')->constrained('financements')->cascadeOnDelete();
            $table->foreignId('composante_id')->nullable()->constrained('composantes')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->constrained('activites')->nullOnDelete();

            $table->date('date_annonce');
            $table->foreignId('bailleur_id')->nullable()->constrained('organismes_contributeurs')->nullOnDelete();

            $table->decimal('montant', 18, 2);
            $table->enum('devise', ['AR', 'USD', 'EUR'])->default('AR');
            $table->decimal('montant_mga', 18, 2);

            $table->text('description')->nullable();
            $table->string('source')->nullable(); // ex: COP, communiqué officiel...

            $table->string('justificatif_path')->nullable();
            $table->string('justificatif_name')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_pledges');
    }
};

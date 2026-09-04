<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un Financement peut désormais avoir plusieurs "Organismes
     * contributeurs" (co-financeurs additionnels), chacun avec son propre
     * montant (numéraire OU en nature) et son propre mode de contribution —
     * indépendant du mode de contribution de la "Source du financement"
     * principale (portée par la table financements elle-même).
     *
     * Mêmes champs "en nature" que sur financements (catégorie, méthode
     * d'évaluation), dupliqués ici car ils s'appliquent par ligne de
     * contribution et non plus au financement dans son ensemble.
     */
    public function up(): void
    {
        Schema::create('financement_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financement_id')->constrained('financements')->cascadeOnDelete();
            $table->foreignId('organisme_contributeur_id')->constrained('organismes_contributeurs')->restrictOnDelete();

            $table->enum('mode_contribution', ['numeraire', 'nature']);

            // Montant (numéraire) ou valeur estimée (nature) — mêmes 3
            // colonnes que sur financements, réutilisées pour les 2 modes.
            $table->decimal('montant', 18, 2);
            $table->enum('devise', ['AR', 'USD', 'EUR']);
            $table->decimal('montant_mga', 18, 2);
            $table->date('date_contribution'); // date d'approbation OU de mise à disposition

            // Champs spécifiques "en nature"
            $table->foreignId('categorie_contribution_id')->nullable()
                ->constrained('contribution_categories')->nullOnDelete();
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financement_contributions');
    }
};

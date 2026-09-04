<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Module de suivi du cycle de vie des budgets climatiques — étape 2 :
     * "Budgets mobilisés" (Mobilised Finance).
     *
     * On réutilise la table `financement_contributions` déjà existante
     * (co-financeurs d'un Financement : organisme + montant + date +
     * catégorie) plutôt que d'en créer une nouvelle en doublon : une ligne
     * de contribution EST un montant mobilisé grâce à un co-financeur. On
     * ajoute simplement les colonnes propres au suivi de cycle (type de
     * mobilisation, commentaire, justificatif, rattachement optionnel à une
     * composante/activité) sans toucher aux colonnes existantes utilisées
     * par le formulaire Financement (aucune régression sur l'écran actuel).
     */
    public function up(): void
    {
        Schema::table('financement_contributions', function (Blueprint $table) {
            $table->foreignId('composante_id')->nullable()->after('financement_id')
                ->constrained('composantes')->nullOnDelete();
            $table->foreignId('activite_id')->nullable()->after('composante_id')
                ->constrained('activites')->nullOnDelete();

            $table->enum('type_mobilisation', ['public', 'prive', 'cofinancement', 'effet_levier'])
                ->nullable()->after('mode_contribution');

            $table->text('commentaire')->nullable()->after('description');
            $table->string('justificatif_path')->nullable()->after('commentaire');
            $table->string('justificatif_name')->nullable()->after('justificatif_path');
        });
    }

    public function down(): void
    {
        Schema::table('financement_contributions', function (Blueprint $table) {
            $table->dropForeign(['composante_id']);
            $table->dropForeign(['activite_id']);
            $table->dropColumn([
                'composante_id', 'activite_id', 'type_mobilisation',
                'commentaire', 'justificatif_path', 'justificatif_name',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache chaque dépense à une période "Année + Semestre" (S1/S2), en plus
 * de la relation Projet → Composante → Activité déjà en place (colonnes
 * composante_id/activite_id ajoutées par 2026_08_06_100600). La date de
 * dépense (`date`) reste inchangée et continue de servir de date précise ;
 * `annee`/`semestre` deviennent le repère de période exigé pour le suivi
 * budgétaire semestriel (§5 du cahier des charges).
 *
 * Les dépenses déjà enregistrées sont rétro-complétées à partir de leur
 * `date` existante (aucune perte de donnée, aucune saisie manuelle requise).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->smallInteger('annee')->nullable()->after('date');
            $table->enum('semestre', ['S1', 'S2'])->nullable()->after('annee');
        });

        // Rétro-remplissage : année = année de la date, semestre = S1 si
        // mois <= 6 sinon S2.
        DB::table('depenses')->whereNull('annee')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                if (!$row->date) {
                    continue;
                }
                $date = \Carbon\Carbon::parse($row->date);
                DB::table('depenses')->where('id', $row->id)->update([
                    'annee'    => $date->year,
                    'semestre' => $date->month <= 6 ? 'S1' : 'S2',
                ]);
            }
        });

        Schema::table('depenses', function (Blueprint $table) {
            $table->index(['project_id', 'annee', 'semestre']);
            $table->index(['composante_id', 'activite_id']);
        });
    }

    public function down(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'annee', 'semestre']);
            $table->dropIndex(['composante_id', 'activite_id']);
            $table->dropColumn(['annee', 'semestre']);
        });
    }
};

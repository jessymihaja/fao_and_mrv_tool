<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correction multidevises (cahier des charges §9-10) : les dépenses ne
 * peuvent être comparées à un budget "dans la même devise" que si ce
 * budget en a une. Or `composantes.budget` et `activites.budget` étaient
 * de simples decimal sans aucune notion de devise, contrairement aux 10
 * tables financières déjà converties (cf. 2026_08_24_080500). Cette
 * migration comble cet oubli, en réutilisant la table `currencies`
 * existante plutôt qu'en recréant une logique de devise séparée (§15).
 *
 * Colonne nullable + backfill à 'AR' (comportement observé jusqu'ici :
 * ces budgets étaient de fait saisis et affichés sans conversion, donc
 * implicitement en Ariary comme la majorité des autres montants par
 * défaut dans le système — cf. defaults 'AR' sur budget_pledges,
 * budget_approbations, engagements, etc.). Aucune donnée existante n'est
 * supprimée ni modifiée en valeur, seule la devise est explicitée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('composantes', function (Blueprint $table) {
            $table->char('devise', 3)->nullable()->after('budget');
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->char('devise', 3)->nullable()->after('budget');
        });

        // Backfill : toutes les composantes/activités existantes qui ont un
        // budget renseigné sont considérées en Ariary (voir docblock).
        DB::table('composantes')->whereNotNull('budget')->update(['devise' => 'AR']);
        DB::table('activites')->whereNotNull('budget')->update(['devise' => 'AR']);

        Schema::table('composantes', function (Blueprint $table) {
            $table->foreign('devise', 'fk_composantes_devise_currencies')
                ->references('code')->on('currencies');
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->foreign('devise', 'fk_activites_devise_currencies')
                ->references('code')->on('currencies');
        });
    }

    public function down(): void
    {
        Schema::table('composantes', function (Blueprint $table) {
            $table->dropForeign('fk_composantes_devise_currencies');
            $table->dropColumn('devise');
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->dropForeign('fk_activites_devise_currencies');
            $table->dropColumn('devise');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Référentiels du module "Parties prenantes" — même schéma (id,
     * designation) et même mécanisme d'ajout en ligne que les référentiels
     * déjà existants (Classification, Secteur...), pour rester cohérent
     * avec le reste de l'application.
     */
    public function up(): void
    {
        Schema::create('stakeholder_categories', function (Blueprint $table) {
            $table->id();
            $table->string('designation')->unique();
            $table->timestamps();
        });

        Schema::create('stakeholder_roles', function (Blueprint $table) {
            $table->id();
            $table->string('designation')->unique();
            $table->timestamps();
        });

        Schema::create('stakeholder_contribution_types', function (Blueprint $table) {
            $table->id();
            $table->string('designation')->unique();
            $table->timestamps();
        });

        $now = now();

        $categories = [
            'Gouvernement', 'Ministère', 'Collectivité territoriale', 'Bailleur de fonds',
            "Agence d'exécution", 'Partenaire technique', 'ONG', 'Organisation communautaire',
            'Secteur privé', 'Institution financière', 'Université / Centre de recherche',
            'Consultant', 'Bénéficiaire',
        ];
        DB::table('stakeholder_categories')->insert(
            array_map(fn ($d) => ['designation' => $d, 'created_at' => $now, 'updated_at' => $now], $categories)
        );

        $roles = [
            'Porteur du projet', 'Co-porteur', 'Bailleur', 'Co-financeur', "Agence d'exécution",
            'Partenaire technique', 'Mise en œuvre', 'Suivi-évaluation', 'Appui institutionnel', 'Bénéficiaire',
        ];
        DB::table('stakeholder_roles')->insert(
            array_map(fn ($d) => ['designation' => $d, 'created_at' => $now, 'updated_at' => $now], $roles)
        );

        $typesContribution = ['Financière', 'Technique', 'En nature', 'Institutionnelle', 'Ressources humaines', 'Matérielle'];
        DB::table('stakeholder_contribution_types')->insert(
            array_map(fn ($d) => ['designation' => $d, 'created_at' => $now, 'updated_at' => $now], $typesContribution)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stakeholder_contribution_types');
        Schema::dropIfExists('stakeholder_roles');
        Schema::dropIfExists('stakeholder_categories');
    }
};

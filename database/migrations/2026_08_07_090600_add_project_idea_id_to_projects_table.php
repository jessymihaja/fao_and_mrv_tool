<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traçabilité de la conversion Idée de projet → Projet : le Projet créé
     * lors de la conversion conserve l'identifiant de l'idée dont il est
     * issu (exigence explicite du cahier des charges).
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_idea_id')->nullable()->after('id')
                ->constrained('project_ideas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['project_idea_id']);
            $table->dropColumn('project_idea_id');
        });
    }
};

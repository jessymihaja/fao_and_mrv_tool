<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Onglet 3 : Secteurs (sélection multiple)
    public function up(): void
    {
        Schema::create('project_idea_secteur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_idea_id')->constrained()->cascadeOnDelete();
            $table->foreignId('secteur_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_idea_id', 'secteur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_idea_secteur');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Historique des changements de statut du workflow (exigé par le cahier des charges)
    public function up(): void
    {
        Schema::create('project_idea_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_idea_id')->constrained()->cascadeOnDelete();
            $table->string('ancien_statut')->nullable();
            $table->string('nouveau_statut');
            $table->text('commentaire')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_idea_status_history');
    }
};

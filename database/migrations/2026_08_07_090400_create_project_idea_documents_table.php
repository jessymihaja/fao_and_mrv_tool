<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Onglet 7 : Documents (Concept Note, Étude de faisabilité, Budget, Carte, Images...)
    public function up(): void
    {
        Schema::create('project_idea_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_idea_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['concept_note', 'etude_faisabilite', 'budget', 'carte', 'images', 'autre'])->default('autre');
            $table->string('libelle')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_idea_documents');
    }
};

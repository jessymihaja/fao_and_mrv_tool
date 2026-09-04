<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('composantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('nom');
            $table->string('objectif_specifique', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('responsable')->nullable();
            $table->decimal('budget', 18, 2)->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->enum('statut', ['Planifiee', 'En cours', 'Terminee', 'Suspendue'])->default('Planifiee');
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('composantes');
    }
};

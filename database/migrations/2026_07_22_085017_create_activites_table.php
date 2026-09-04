<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('composante_id')->constrained('composantes')->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('responsable')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->decimal('budget', 18, 2)->nullable();
            $table->enum('statut', ['Planifiee', 'En cours', 'Terminee', 'Suspendue'])->default('Planifiee');
            $table->unsignedTinyInteger('pourcentage_avancement')->default(0);
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['composante_id', 'statut']);
        });

        // Pièces jointes des activités (fichiers justificatifs / preuves)
        Schema::create('activite_pieces_jointes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activite_id')->constrained('activites')->cascadeOnDelete();
            $table->string('fichier');
            $table->string('fichier_original')->nullable();
            $table->unsignedBigInteger('taille')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activite_pieces_jointes');
        Schema::dropIfExists('activites');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('indicateur_evolutions', function (Blueprint $table) {
            $table->id('id_indicateur_evolution');
            
            // Cle etrangere vers la table indicateurs (ajuste 'id_indicateur' ou 'indicateurs' selon ton nommage)
            $table->foreignId('indicateur_id')
                  ->constrained('indicateurs', 'id')
                  ->onDelete('cascade');
                  
            $table->integer('annee');
            $table->decimal('montant', 15, 2); // 15 chiffres dont 2 decimales
            $table->timestamps();
            
            // Empeche d'avoir deux fois la meme annee pour un meme indicateur
            $table->unique(['indicateur_id', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicateur_evolutions');
    }
};

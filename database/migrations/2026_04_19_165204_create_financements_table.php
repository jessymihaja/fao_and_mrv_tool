<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('financements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('source_financement');           // Nom du financeur
            $table->decimal('budget_approuve', 20, 2);      // Montant approuvé
            $table->enum('devise', ['AR', 'USD', 'EUR'])->default('USD');
            $table->decimal('montant_mga', 20, 2);          // Équivalent en MGA
            $table->date('date_approbation');               // Date de financement
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('financements');
    }
};
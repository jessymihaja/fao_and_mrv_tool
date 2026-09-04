<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->enum('statut', ['Concept Note', 'Funding Proposal', 'En cours', 'Clôturé'])->default('Concept Note');
            $table->text('description')->nullable();
            $table->enum('secteur_climatique', ['adaptation','attenuation','resilience','biodiversite','eau','foret','energie','transport','agriculture'])->nullable();
            $table->string('classification')->nullable();
            $table->string('accredited_entity')->nullable();
            $table->string('geo_address', 500)->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fokontany_id')->nullable()->constrained('fokontany')->nullOnDelete();
            $table->text('zone_description')->nullable();
            $table->text('objectifs')->nullable();
            $table->text('impact')->nullable();
            $table->text('problematique_climatique')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('projects');
    }
};
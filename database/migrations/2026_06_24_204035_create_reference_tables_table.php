<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id('id_status');
            $table->string('designation', 100)->unique();
            $table->timestamps();
        });

        Schema::create('classifications', function (Blueprint $table) {
            $table->id('id_classification');
            $table->string('designation', 100)->unique();
            $table->timestamps();
        });

        Schema::create('domaine_interventions', function (Blueprint $table) {
            $table->id('id_domaine_intervention');
            $table->string('designation', 150)->unique();
            $table->timestamps();
        });

        Schema::create('entite_accreditees', function (Blueprint $table) {
            $table->id('id_entite_accreditee');
            $table->string('designation', 200);
            $table->string('sigle', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entite_accreditees');
        Schema::dropIfExists('domaine_interventions');
        Schema::dropIfExists('classifications');
        Schema::dropIfExists('statuses');
    }
};

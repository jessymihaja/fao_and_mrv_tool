<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un indicateur ou un document peut désormais être rattaché soit
        // directement au projet (comme avant), soit à une composante précise.
        Schema::table('indicateurs', function (Blueprint $table) {
            $table->foreignId('composante_id')->nullable()->after('project_id')
                ->constrained('composantes')->cascadeOnDelete();
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('composante_id')->nullable()->after('project_id')
                ->constrained('composantes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('indicateurs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('composante_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('composante_id');
        });
    }
};

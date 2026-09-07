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
        Schema::table('financements', function (Blueprint $table) {
            // Ajoute la colonne enum avec les valeurs spécifiées
            $table->enum('statut', [
                'subvention',
                'pret',
                'credit', 
                'don'
            ])->nullable()->after('id'); 
            // Vous pouvez modifier ->after('id') pour placer la colonne après un autre champ (ex: after('montant'))
            // Retirez ->nullable() si la valeur est obligatoire à la création
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financements', function (Blueprint $table) {
            $table->dropColumn('statut');
        });
    }
};

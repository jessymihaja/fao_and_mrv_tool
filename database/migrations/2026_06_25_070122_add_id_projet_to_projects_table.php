<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('id_projet', 50)
                  ->nullable()
                  ->unique()
                  ->after('id')
                  ->comment('Identifiant lisible du projet, ex: GCF-2024-001');
        });

        // Générer un id_projet pour les projets existants
        $projects = DB::table('projects')->orderBy('id')->get(['id']);
        foreach ($projects as $i => $p) {
            $num = str_pad($i + 1, 3, '0', STR_PAD_LEFT);
            DB::table('projects')->where('id', $p->id)->update([
                'id_projet' => 'GCF-' . date('Y') . '-' . $num,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['id_projet']);
            $table->dropColumn('id_projet');
        });
    }
};

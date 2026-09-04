<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Engagements ───────────────────────────────────────────────────────
        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financement_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('montant', 20, 2);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Plans de décaissement ─────────────────────────────────────────────
        Schema::create('decaissement_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financement_id')->constrained()->cascadeOnDelete();
            $table->date('date_prevue');
            $table->decimal('montant_prevu', 20, 2);
            $table->enum('statut', ['prevu', 'effectue'])->default('prevu');
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Décaissements réels ───────────────────────────────────────────────
        Schema::create('decaissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financement_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('montant', 20, 2);
            $table->string('reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Ajouter financement_id + categorie + reference aux dépenses ───────
        Schema::table('depenses', function (Blueprint $table) {
            $table->foreignId('financement_id')->nullable()->constrained()->nullOnDelete()->after('project_id');
            $table->string('categorie')->nullable()->after('beneficiaire');
            $table->string('reference')->nullable()->after('categorie');
        });
    }

    public function down(): void
    {
        Schema::table('depenses', function (Blueprint $table) {
            $table->dropForeign(['financement_id']);
            $table->dropColumn(['financement_id', 'categorie', 'reference']);
        });
        Schema::dropIfExists('decaissements');
        Schema::dropIfExists('decaissement_plans');
        Schema::dropIfExists('engagements');
    }
};
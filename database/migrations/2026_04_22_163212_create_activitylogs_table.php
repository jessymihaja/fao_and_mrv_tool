<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');        // create, update, delete, upload, login…
            $table->string('module');        // project, financement, document, user…
            $table->text('description')->nullable();
            $table->unsignedBigInteger('project_id')->nullable(); // référence souple, pas de FK stricte
            $table->unsignedBigInteger('subject_id')->nullable(); // id de l'entité concernée
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
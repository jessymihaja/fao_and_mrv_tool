<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->text('reponse');
            $table->string('categorie')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('abbr', 10)->nullable();
            $table->string('color', 20)->nullable()->default('#1a6b45');
            $table->string('logo')->nullable();
            $table->string('url')->nullable();
            $table->text('description')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('email');
            $table->string('sujet');
            $table->text('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        Schema::create('sliders', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('sous_titre')->nullable();
            $table->string('image')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('cta_url')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('public_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
        });

        Schema::create('chatbot_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('welcome_message', 500)->nullable()->default('Bonjour ! Comment puis-je vous aider ?');
            $table->timestamps();
        });

        Schema::create('chatbot_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->text('keywords');
            $table->text('response');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('chatbot_knowledge');
        Schema::dropIfExists('chatbot_settings');
        Schema::dropIfExists('public_settings');
        Schema::dropIfExists('sliders');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('faqs');
    }
};
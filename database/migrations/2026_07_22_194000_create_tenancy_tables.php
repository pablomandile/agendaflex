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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('public_key')->unique();
            $table->string('timezone')->default('America/Argentina/Buenos_Aires');
            $table->string('locale', 10)->default('es');
            $table->string('currency', 3)->default('ARS');
            $table->string('status')->default('active'); // active | suspended
            $table->json('settings')->nullable();
            // Dominios permitidos para embeber el widget (validación de Origin)
            $table->json('allowed_origins')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('timezone')->nullable(); // null => hereda de la empresa
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
        });

        // Membresía: a qué empresas pertenece cada usuario (los roles por
        // empresa viven en model_has_roles de spatie, con company_id)
        Schema::create('company_user', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['company_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};

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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            // Vínculo opcional a cuenta con login (rol staff)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid()->unique(); // identificador público (API/widget)
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('color', 7)->default('#6366f1'); // color en el calendario
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id']);
        });

        // Skills: qué servicios da cada empleado (+ overrides opcionales)
        Schema::create('employee_service', function (Blueprint $table) {
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('custom_duration_minutes')->nullable();
            $table->decimal('custom_price', 10, 2)->nullable();

            $table->primary(['employee_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_service');
        Schema::dropIfExists('employees');
    }
};

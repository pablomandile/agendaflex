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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->uuid()->unique(); // identificador público (links firmados)
            $table->dateTime('starts_at'); // UTC
            $table->dateTime('ends_at');   // UTC
            // pending|confirmed|cancelled|completed|no_show
            $table->string('status')->default('confirmed');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3);
            $table->string('source')->default('panel'); // panel|widget
            $table->text('notes')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            // Trazabilidad de reprogramaciones (self-FK al turno original)
            $table->foreignId('rescheduled_from_id')->nullable()
                ->constrained('appointments')->nullOnDelete();
            $table->timestamps();

            // Índices clave para disponibilidad y calendario
            $table->index(['company_id', 'employee_id', 'starts_at']);
            $table->index(['company_id', 'branch_id', 'starts_at']);
            $table->index(['company_id', 'customer_id']);
        });

        // Recursos ocupados por el turno durante [starts_at, ends_at]
        Schema::create('appointment_resource', function (Blueprint $table) {
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();

            $table->primary(['appointment_id', 'resource_id']);
        });

        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            // null = cualquier empleado que dé el servicio
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('desired_from'); // UTC
            $table->dateTime('desired_to');   // UTC
            $table->string('status')->default('waiting'); // waiting|notified|converted|expired
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'service_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
        Schema::dropIfExists('appointment_resource');
        Schema::dropIfExists('appointments');
    }
};

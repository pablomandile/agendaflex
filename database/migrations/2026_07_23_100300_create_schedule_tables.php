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
        // Horario recurrente semanal por empleado. Varias filas por día
        // modelan turnos partidos (mañana/tarde). Los horarios se expresan
        // en la zona horaria de la sucursal del empleado.
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=domingo ... 6=sábado
            $table->time('start_time');
            $table->time('end_time');
            // Vigencia opcional: permite cambios de horario programados
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'day_of_week']);
        });

        // Excepciones a la agenda: vacaciones/ausencias (employee_id),
        // feriado de sucursal (branch_id sin employee), cierre de empresa
        // (ambos null) o disponibilidad extra puntual (type=extra).
        Schema::create('time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at'); // UTC
            $table->dateTime('ends_at');   // UTC
            $table->string('type')->default('block'); // vacation|holiday|sick|block|extra
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'starts_at']);
            $table->index(['company_id', 'branch_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_off');
        Schema::dropIfExists('working_hours');
    }
};

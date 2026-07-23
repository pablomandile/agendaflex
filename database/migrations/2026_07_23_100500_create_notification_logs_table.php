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
        // Auditoría de notificaciones enviadas (qué, a quién, cuándo, resultado)
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('channel')->default('email'); // email (sms/whatsapp fase 2)
            $table->string('type'); // confirmation|reminder|cancellation|reschedule|waitlist
            $table->string('recipient');
            $table->string('status')->default('queued'); // queued|sent|failed
            $table->dateTime('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};

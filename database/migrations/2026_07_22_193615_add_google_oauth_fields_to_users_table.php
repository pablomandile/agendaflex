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
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar')->nullable()->after('google_id');
            // Cuentas creadas vía Google no tienen contraseña propia
            $table->string('password')->nullable()->change();
            // Rol de plataforma (nivel superior al rol por empresa)
            $table->boolean('is_super_admin')->default(false)->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar', 'is_super_admin']);
            $table->string('password')->nullable(false)->change();
        });
    }
};

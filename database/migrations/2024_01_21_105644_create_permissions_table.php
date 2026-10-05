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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('role_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->json('permissions')->comment('edit permissions permission');
            $table->json('settings');
            $table->json('users');
            $table->json('claims');
            $table->json('offices');
            $table->json('response'); // recall or resend SMS
            $table->json('recovery_target');
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};

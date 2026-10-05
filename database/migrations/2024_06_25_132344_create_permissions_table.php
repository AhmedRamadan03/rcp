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
        Schema::dropIfExists('permissions');

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
            $table->json('claims');
            $table->json('claims_files');
            $table->json('payment_link'); // recall or resend SMS
            $table->json('reject_claims');
            $table->json('settings');
            $table->json('third_parties');
            $table->json('users');
            $table->json('permissions')->comment('edit permissions permission');
            $table->json('role');

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

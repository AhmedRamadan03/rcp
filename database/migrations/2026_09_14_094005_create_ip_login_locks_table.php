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
        Schema::create('ip_login_locks', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address')->unique();
            $table->unsignedInteger('failed_attempts')->default(0);
            $table->unsignedInteger('block_count')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_login_locks');
    }
};
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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();

            $table->foreignId('client_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->text('profile_image')->nullable();
            $table->foreignId('role_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->text('bio')->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

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
        Schema::dropIfExists('delegations');
        Schema::create('delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from')
                ->references('id')->on('users')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('to')
                ->references('id')->on('users')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delegations');
    }
};

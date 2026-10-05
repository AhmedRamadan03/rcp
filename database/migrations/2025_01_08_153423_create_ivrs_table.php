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
        Schema::create('ivrs', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->string('callId');
            $table->string('referenceId');
            $table->string('payment_link_id');
            $table->boolean('is_checked')->default(false);
            $table->integer('retry_number')->default(1);
            $table->string('audio_id');
            $table->json('response')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ivrs');
    }
};

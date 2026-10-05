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
        Schema::create('change_claim_amount_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('status_change_requests');
            $table->double('current_amount');
            $table->double('new_amount');
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_claim_amount_requests');
    }
};

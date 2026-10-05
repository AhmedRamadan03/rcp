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
        Schema::create('elm_requests', function (Blueprint $table) {
            $table->id();
            $table->Integer('claim_id');
            $table->text('batch_number')->nullable();
            $table->string('status')->nullable();
            $table->string('batch_identifier')->nullable();
            $table->string('national_id')->nullable();
            $table->string('sms_description')->nullable();
            $table->string('link')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('otp')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('elm_requests');
    }
};

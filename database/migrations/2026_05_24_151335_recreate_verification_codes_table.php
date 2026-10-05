<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('verification_codes');
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();

            // Polymorphic: links to either User or Claim(deptor phone verification)
            $table->morphs('otpable'); // creates otpable_id (bigInt) + otpable_type (string)

            $table->string('otp');
            $table->string('purpose'); // 'two_factor' | 'phone_verify'
            $table->string('phone')->nullable(); // the phone the OTP was sent to
            $table->timestamp('expire_at')->nullable();
            $table->timestamp('verified_at')->nullable(); // null = not yet verified
            $table->timestamps();

            $table->index(['otpable_type', 'otpable_id', 'purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_codes');

        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id');
            $table->string('otp');
            $table->timestamp('expire_at')->nullable();
            $table->timestamps();
        });
    }
};

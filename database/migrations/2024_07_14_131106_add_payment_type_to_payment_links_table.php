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
        Schema::table('payment_links', function (Blueprint $table) {
            //
            $table->enum('type', [
                \App\Enums\PaymentType::FULL_PAYMENT->value,
                \App\Enums\PaymentType::DELAY_PAYMENT->value,
                \App\Enums\PaymentType::INSTALLMENT_PAYMENT->value,
            ])->default(\App\Enums\PaymentType::FULL_PAYMENT->value);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_links', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

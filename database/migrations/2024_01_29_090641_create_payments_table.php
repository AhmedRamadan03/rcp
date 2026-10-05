<?php

use App\Enums\PaymentStatus;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')
                ->references('id')->on('claims')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->double('amount', 8, 2);
            $table->double('paid_amount', 8, 2)->default(00.00);
            $table->enum('payment_method', [
                'credit card',
                'bank transfer',
            ])->nullable();
            $table->enum('payment_status', [
                'paid',
                'pending',
                'scheduled',
                'delayed',
                'rejected',
                'canceled',
                'modified',
                'unpaid',
            ])->default(PaymentStatus::PENDING);
            $table->datetime('due_date')->nullable();
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

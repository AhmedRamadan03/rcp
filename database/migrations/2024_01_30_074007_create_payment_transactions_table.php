<?php

use App\Enums\PaymentTransactionStatus;
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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->references('id')->on('payments')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('link_id')
                ->references('id')->on('payment_links')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->enum('send_status', [
                'created',
                'paid',
                'canceled',
                'failed',
            ])->default(PaymentTransactionStatus::CREATED);

            $table->double('amount', 8, 2);

            $table->json('request')->nullable();
            $table->json('response')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};

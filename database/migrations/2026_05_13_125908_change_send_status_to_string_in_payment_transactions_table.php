<?php

use App\Enums\PaymentTransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the check constraint PostgreSQL auto-created for the enum
        DB::statement('ALTER TABLE payment_transactions DROP CONSTRAINT IF EXISTS payment_transactions_send_status_check');

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('send_status')->default('created')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->enum('send_status', [
                'created',
                'paid',
                'canceled',
                'failed',
            ])
                ->default(PaymentTransactionStatus::CREATED->value)
                ->change();
        });
    }
};
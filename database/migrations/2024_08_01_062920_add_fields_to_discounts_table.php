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
        Schema::table('discounts', function (Blueprint $table) {
            //
            $table->foreignId('claim_id')
                ->nullable()
                ->references('id')->on('claims')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('payment_id')
                ->nullable()
                ->references('id')->on('payments')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->double('total_amount', 8, 2)->default(0);
            $table->double('amount_after_discount', 8, 2)->default(0);
            $table->double('discount_percentage', 8, 2)->default(0);

            $table->tinyInteger('is_active')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            //
            $table->dropForeign('discounts_payment_id_foreign');
            $table->dropForeign('discounts_claim_id_foreign');
            $table->dropColumn('payment_id');
            $table->dropColumn('claim_id');
            $table->dropColumn('payment_id');
            $table->dropColumn('total_amount');
            $table->dropColumn('amount_after_discount');
            $table->dropColumn('discount_percentage');
            $table->dropColumn('is_active');

        });
    }
};

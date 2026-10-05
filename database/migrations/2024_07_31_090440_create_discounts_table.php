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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            //            $table->foreignId('claim_id')
            //                ->nullable()
            //                ->references('id')->on('claims')
            //                ->constrained()
            //                ->onUpdate('cascade')
            //                ->onDelete('cascade');
            //
            //            $table->foreignId('payment_id')
            //                ->nullable()
            //                ->references('id')->on('payments')
            //                ->constrained()
            //                ->onUpdate('cascade')
            //                ->onDelete('cascade');
            //
            //            $table->double('total_amount', 8, 2)->default(0);
            //            $table->double('amount_after_discount', 8, 2)->default(0);
            //            $table->double('discount_percentage')->default(0);
            //
            //            $table->tinyInteger('is_active')->default(0);
            //            $table->double('deducted_amount', 8, 2)->default(0);
            //            $table->unsignedBigInteger('request_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};

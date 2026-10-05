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
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')
                ->references('id')->on('status_change_requests')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->integer('link_id');
            $table->integer('link_amount');
            $table->string('link_status');
            $table->integer('link_order');
            $table->date('link_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};

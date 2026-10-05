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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->text('body');
            $table->text('send_date');
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->integer('sent_count')->default(0);
            $table->enum('status', [
                'sended',
                'not sended',
                'delivered',
                'not delivered',
            ]);
            $table->text('phone_number')->nullable();
            $table->text('status_message')->nullable();
            $table->text('message_status')->nullable();
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

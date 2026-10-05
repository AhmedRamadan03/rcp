<?php

use App\Enums\LinkSendStatus;
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
        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->references('id')->on('payments')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->enum('send_status', [
                'not sent',
                'sent',
                'paid',
                'canceled',
                'expired',
                'failed',
            ])->default(LinkSendStatus::NOT_SENT->value);

            $table->string('link');

            $table->double('amount', 8, 2);

            $table->integer('views_count')->default(0);
            $table->timestamp('last_access')->nullable();
            $table->date('due_date');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};

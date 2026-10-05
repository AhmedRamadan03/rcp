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
        Schema::create('third_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->references('id')->on('clients')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('type'); // 'sms', 'payment', 'IVR'
            $table->string('vendor_name');
            $table->string('vendor_label');
            $table->boolean('is_active');
            $table->json('values');
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('third_parties');
    }
};

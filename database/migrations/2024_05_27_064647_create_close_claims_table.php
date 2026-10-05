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
        Schema::create('close_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')
                ->nullable()
                ->references('id')
                ->on('claims')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('reason_id')
                ->nullable()
                ->references('id')
                ->on('close_reasons')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->text('url');
            $table->text('other_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('close_claims');
    }
};

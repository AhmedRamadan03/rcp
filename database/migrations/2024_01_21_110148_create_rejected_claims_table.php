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
        Schema::create('rejected_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('user_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade')
                ->comment('the last assigned user');

            $table->foreignId('supervisor_id')
                ->references('id')->on('users')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade')
                ->comment('the supervisor who approve this action');

            $table->text('reason');
            $table->enum('type', ['employee', 'user']);
            $table->enum('status', [
                'accepted',
                'rejected',
                'not seen',
            ])
                ->default('not seen');
            $table->json('files')->nullable();
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rejected_claims');
    }
};

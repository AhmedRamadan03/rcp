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
        if (! Schema::hasTable('claim_comments')) {
            Schema::create('claim_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable();

                $table->foreignId('claim_id')
                    ->references('id')->on('claims')
                    ->constrained()
                    ->onUpdate('cascade')
                    ->onDelete('cascade');

                $table->text('body')->nullable();

                $table->boolean('is_deleted')->default(false);
                $table->timestamps();

                $table->softDeletesTz($column = 'deleted_at', $precision = 0);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_comments');
    }
};

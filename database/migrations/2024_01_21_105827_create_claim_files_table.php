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
        Schema::create('claim_files', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('url');
            $table->foreignId('claim_id')
                ->references('id')->on('claims')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->enum('access', [
                'public',
                'private',
            ]);
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cliam_files');
    }
};

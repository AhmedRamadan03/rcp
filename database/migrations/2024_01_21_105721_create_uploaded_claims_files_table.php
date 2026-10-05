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
        Schema::create('uploaded_claims_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade')
                ->comment('becaus claims my uploaded by officer user');
            $table->foreignId('user_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->text('file_url')->comment('exports file only');
            $table->enum('uploaded_claims_status', [
                'inserted',
                'uploaded',
                'invalid',
                'scheduled',
            ])->default('inserted');
            $table->dateTime('inserted_date');
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploaded_claims_files');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rejected_claim_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rejected_claim_id')
                ->constrained('rejected_claims')->cascadeOnDelete();
            $table->foreignId('file_id')
                ->constrained('files')->cascadeOnDelete();
            $table->text('file_path')->nullable();
            $table->timestamps();
            $table->unique(['rejected_claim_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rejected_claim_files');
    }
};

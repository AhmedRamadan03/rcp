<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_risk_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->decimal('risk_score', 5, 2);
            $table->json('risk_factors');
            $table->enum('trigger', ['created', 'updated', 'manual_recalc', 'status_change'])->default('updated');
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['claim_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_risk_histories');
    }
};
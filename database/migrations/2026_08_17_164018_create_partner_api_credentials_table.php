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
        Schema::create('partner_api_credentials', function (Blueprint $table) {
            $table->id();
 
 
            $table->string('api_client_id')->unique();
            $table->string('client_secret_hash');
 
            $table->enum('status', ['active', 'inactive'])->default('active');
 
            $table->foreignId('created_by')->constrained('users')->nullOnDelete();
 
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
 
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_api_credentials');
    }
};
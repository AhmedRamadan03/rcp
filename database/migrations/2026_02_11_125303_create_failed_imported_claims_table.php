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
        Schema::create('failed_imported_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')
                ->constrained('uploaded_claims_files')
                ->onDelete('cascade');
            $table->integer('row_number');
            $table->json('row_data');
            $table->json('error_message');
            $table->timestamps();

            $table->index('file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_imported_claims');
    }
};

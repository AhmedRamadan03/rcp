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
        Schema::table('additional_files', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('additional_files', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by']);
            $table->dropColumn('uploaded_by');
        });
    }
};

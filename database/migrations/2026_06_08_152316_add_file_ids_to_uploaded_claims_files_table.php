<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_claims_files', function (Blueprint $table) {
            $table->foreignId('original_file_id')->nullable()->after('id')
                ->constrained('files')->nullOnDelete();
            $table->foreignId('failed_file_id')->nullable()->after('original_file_id')
                ->constrained('files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_claims_files', function (Blueprint $table) {
            $table->dropForeign(['original_file_id']);
            $table->dropForeign(['failed_file_id']);
            $table->dropColumn(['original_file_id', 'failed_file_id']);
        });
    }
};

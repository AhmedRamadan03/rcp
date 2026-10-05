<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the check constraint first (PostgreSQL specific)
        DB::statement('ALTER TABLE uploaded_claims_files DROP CONSTRAINT IF EXISTS uploaded_claims_files_uploaded_claims_status_check');
        
        Schema::table('uploaded_claims_files', function (Blueprint $table) {
            // Change uploaded_claims_status from enum to string
            $table->string('uploaded_claims_status')->change();
            
            // Add tracking fields for import results
            $table->integer('success_count')->nullable()->after('uploaded_claims_status');
            $table->integer('failed_count')->nullable()->after('success_count');
            $table->string('failed_file_path')->nullable()->after('failed_count');
            $table->text('error_message')->nullable()->after('failed_file_path');
            $table->timestamp('completed_at')->nullable()->after('error_message');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uploaded_claims_files', function (Blueprint $table) {
            // Remove tracking fields
            $table->dropColumn([
                'success_count',
                'failed_count',
                'failed_file_path',
                'error_message',
                'completed_at',
            ]);
            
            // Revert uploaded_claims_status back to enum
            $table->enum('uploaded_claims_status', [
                'pending',
                'failed',
                'processing',
                'completed'
            ])->change();
        });
        
        // Note: The check constraint will be recreated automatically by the enum definition

    }
};

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
         Schema::table('claims', function (Blueprint $table) {

            if (!Schema::hasColumn('claims', 'reference')) {
                $table->string('reference')->nullable();
                // Non-unique index for now (search/lookups), unique constraint
                // is added later once backfill is verified duplicate-free.
                $table->unique('reference', 'claims_reference_index');
            }
            if (!Schema::hasColumn('claims', 'external_reference')) {
                $table->string('external_reference')->nullable();
                $table->unique('external_reference', 'claims_external_reference_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('claims', function (Blueprint $table) {
            $table->dropUnique('claims_reference_index');
            $table->dropColumn('reference');
            $table->dropUnique('claims_external_reference_index');
            $table->dropColumn('external_reference');
        });
    }
};
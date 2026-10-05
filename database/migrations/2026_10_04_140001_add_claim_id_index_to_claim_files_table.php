<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX = 'claim_files_claim_id_index';

    /**
     * CREATE INDEX CONCURRENTLY cannot run inside a transaction.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A failed concurrent build leaves an INVALID index that IF NOT EXISTS would skip.
        $invalid = DB::selectOne(
            'select 1 from pg_index where indexrelid = to_regclass(?) and not indisvalid',
            [self::INDEX]
        );

        if ($invalid) {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::INDEX);
        }

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ' . self::INDEX . ' ON claim_files (claim_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::INDEX);
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
        ALTER TABLE payment_links
        ALTER COLUMN send_status TYPE VARCHAR
        USING send_status::varchar
    ");

        DB::statement("
        ALTER TABLE payment_links
        ALTER COLUMN send_status SET DEFAULT 'not sent'
    ");

        DB::statement("
        ALTER TABLE payment_links
        DROP CONSTRAINT IF EXISTS payment_links_send_status_check
    ");
    }

    public function down(): void
    {
        // لو عندك enum فعلاً موجود في الداتا بيز
        DB::statement("
        ALTER TABLE payment_links
        ALTER COLUMN send_status TYPE payment_links_send_status_enum
        USING send_status::payment_links_send_status_enum
    ");
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
        ALTER TABLE messages
        ALTER COLUMN status TYPE VARCHAR
        USING status::varchar
    ");

      

        DB::statement("
        ALTER TABLE messages
        DROP CONSTRAINT IF EXISTS messages_status_check
    ");
    }

    public function down(): void
    {
        // لو عندك enum فعلاً موجود في الداتا بيز
        DB::statement("
        ALTER TABLE messages
        ALTER COLUMN status TYPE messages_status_enum
        USING status::messages_status_enum
    ");
    
    }
};

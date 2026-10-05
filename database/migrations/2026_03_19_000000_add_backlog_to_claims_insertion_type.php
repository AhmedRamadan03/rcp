<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE claims DROP CONSTRAINT IF EXISTS claims_insertion_type_check');
        DB::statement("ALTER TABLE claims ADD CONSTRAINT claims_insertion_type_check CHECK (insertion_type IN ('form', 'exports file', 'api', 'backlog'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE claims DROP CONSTRAINT IF EXISTS claims_insertion_type_check');
        DB::statement("ALTER TABLE claims ADD CONSTRAINT claims_insertion_type_check CHECK (insertion_type IN ('form', 'exports file', 'api'))");
    }
};

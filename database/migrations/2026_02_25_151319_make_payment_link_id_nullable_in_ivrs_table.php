<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ivrs', function (Blueprint $table) {
            DB::statement("
            ALTER TABLE ivrs 
            ALTER COLUMN payment_link_id DROP NOT NULL
        ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ivrs', function (Blueprint $table) {
            DB::statement("
            ALTER TABLE ivrs 
            ALTER COLUMN payment_link_id SET NOT NULL
            ");
        });
    }
};

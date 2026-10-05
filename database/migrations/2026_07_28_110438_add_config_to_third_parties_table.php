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
        Schema::table('third_parties', function (Blueprint $table) {
            $table->json('config')->nullable()->after('vendor_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('third_parties', function (Blueprint $table) {
            $table->dropColumn('config');
        });
    }
};

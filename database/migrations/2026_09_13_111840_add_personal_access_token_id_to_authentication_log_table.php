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
        Schema::table('authentication_log', function (Blueprint $table) {
            $table->unsignedBigInteger('personal_access_token_id')->nullable()->index()->after('authenticatable_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('authentication_log', function (Blueprint $table) {
            $table->dropColumn('personal_access_token_id');
        });
    }
};

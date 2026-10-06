<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->string('risk_version', 20)->nullable()->after('risk_score');
        });

        Schema::table('claim_risk_histories', function (Blueprint $table) {
            $table->string('risk_version', 20)->after('risk_score');
        });
    }

    public function down(): void
    {
        Schema::table('claim_risk_histories', function (Blueprint $table) {
            $table->dropColumn('risk_version');
        });

        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn('risk_version');
        });
    }
};

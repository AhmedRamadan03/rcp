<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->decimal('risk_score', 5, 2)->default(0)->after('status');
            $table->json('risk_factors')->nullable()->after('risk_score');
            $table->timestamp('risk_calculated_at')->nullable()->after('risk_factors');
            $table->index('risk_score');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropIndex(['risk_score']);
            $table->dropColumn(['risk_score', 'risk_factors', 'risk_calculated_at']);
        });
    }
};
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
        Schema::table('template_variables', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('custom_templates', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_variables', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('custom_templates', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

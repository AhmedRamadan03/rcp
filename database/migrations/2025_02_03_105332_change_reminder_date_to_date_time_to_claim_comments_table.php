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
        Schema::dropColumns('claim_comments', ['reminder_date']);

        Schema::table('claim_comments', function (Blueprint $table) {
            $table->dateTime('reminder_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claim_comments', function (Blueprint $table) {
            $table->dropColumn('reminder_date');
        });
    }
};

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
        Schema::table('claim_status_histories', function (Blueprint $table) {
            $table->text('note')->nullable();
            $table->string('file')->nullable();
            $table->text('reason')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claim_status_histories', function (Blueprint $table) {
             $table->dropColumn('note');
             $table->dropColumn('file');
             $table->dropColumn('reason');
        });
    }
};

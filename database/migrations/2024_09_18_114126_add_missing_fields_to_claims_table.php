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
        Schema::table('claims', function (Blueprint $table) {
            //
            $table->string('policy_holder')->nullable();
            $table->date('date_of_loss')->nullable();
            $table->string('accident_number')->nullable();
            $table->double('recovered_amount', 8, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            //
            $table->dropColumn('policy_holder');
            $table->dropColumn('date_of_loss');
            $table->dropColumn('accident_number');
            $table->dropColumn('recovered_amount');
        });
    }
};

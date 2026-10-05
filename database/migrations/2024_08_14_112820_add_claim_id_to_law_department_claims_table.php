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
        Schema::table('law_department_claim', function (Blueprint $table) {
            //
            $table->foreignId('request_id')
                ->nullable()
                ->references('id')->on('status_change_requests')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('claim_id')
                ->nullable()
                ->references('id')->on('claims')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->nullable()
                ->references('id')->on('users')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->tinyInteger('status')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('law_department_claims', function (Blueprint $table) {
            //
        });
    }
};

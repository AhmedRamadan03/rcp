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
        Schema::create('status_change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('from_status');
            $table->string('to_status');
            $table->foreignId('claim_id')
                ->references('id')->on('claims')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('additional_file_id')
                ->nullable()
                ->references('id')->on('additional_files')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->references('id')->on('users')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('supervisor_id')
                ->references('id')->on('users')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->string('request_status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_change_requests');
    }
};

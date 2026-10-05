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
        Schema::create('assignings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')
                ->references('id')->on('claims')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('supervisor_id')
                ->nullable()
                ->references('id')->on('users')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('from')
                ->nullable()
                ->references('id')->on('users')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade'); // if iys null its mean automatically assigned by assigning system

            $table->foreignId('to')
                ->references('id')->on('users')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->boolean('assign_as_office')->default(false);
            $table->text('assign_comment')->nullable();
            $table->dateTime('assign_date')->default(now());
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignings');
    }
};

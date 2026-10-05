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
        Schema::dropIfExists('notifications');
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('title_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->string('description_ar')->nullable();
            $table->string('description_en')->nullable();
            $table->boolean('viewed')->default(false);
            //            $table->boolean("accept")->nullable();
            //            $table->boolean("reject")->nullable();
            $table->json('to')->nullable();
            $table->json('links')->nullable();
            $table->text('regarding');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

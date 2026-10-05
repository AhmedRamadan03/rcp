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
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('template_id')
                ->nullable()
                ->constrained()
                ->references('id')->on('custom_templates')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->integer('messagble_id')->nullable();
            $table->string('messagble_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign('messages_template_id_foreign');
            $table->dropColumn('template_id');
            $table->dropColumn('messagble_id');
            $table->dropColumn('messagble_type');
        });
    }
};

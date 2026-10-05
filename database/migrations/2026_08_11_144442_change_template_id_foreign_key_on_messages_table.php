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
            $table->dropForeign('messages_template_id_foreign');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('template_id')
                ->references('id')->on('message_templates')
                ->onUpdate('cascade')
                ->onDelete('set null'); // preserve history if a template is deleted
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign('messages_template_id_foreign');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreign('template_id')
                ->references('id')->on('custom_templates')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }
};

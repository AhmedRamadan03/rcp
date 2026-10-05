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
        Schema::table('custom_templates', function (Blueprint $table) {
            $table->foreignId('third_party_id')
                ->nullable()
                ->after('template_id')
                ->constrained('third_parties')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->string('provider_template_code')->nullable()->after('third_party_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('third_party_id');
            $table->dropColumn('provider_template_code');
        });
    }
};

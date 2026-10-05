<?php

use App\Enums\CustomTemplateStatus;
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
            $table->string('name')->after('template_id');

            // unique constraint on name to be unique per template
            $table->unique(['template_id', 'name'], 'custom_templates_template_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_templates', function (Blueprint $table) {

            $table->dropUnique('custom_templates_template_name_unique');
            // Drop 'name' column
            $table->dropColumn('name');
        });
    }
};

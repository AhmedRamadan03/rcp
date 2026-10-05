<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('custom_template_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_template_id')
                ->constrained('custom_templates')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreignId('template_variable_id')
                ->constrained('template_variables')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->string('provider_key');
            $table->timestamps();

            $table->unique(
                ['custom_template_id', 'template_variable_id'],
                'custom_template_provider_configs_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_template_provider_configs');
    }
};

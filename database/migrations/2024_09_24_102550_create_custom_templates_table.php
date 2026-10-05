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
        Schema::create('custom_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')
                ->constrained()
                ->references('id')->on('message_templates')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->text('body');

            $table->enum('is_active', [
                CustomTemplateStatus::ACTIVE->value,
                CustomTemplateStatus::INACTIVE->value,
            ])->default(CustomTemplateStatus::INACTIVE);

            $table->foreignId('client_id')
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_templates');
    }
};

<?php

use App\Enums\DataHistoryEnum;
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
        Schema::create('data_histories', function (Blueprint $table) {
            $table->id();
            $table->string('ref');
            $table->text('title');
            $table->string('type');
            $table->string('file_path')->nullable();
            $table->string('status')->default(DataHistoryEnum::PROCESSING->value);
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_histories');
    }
};

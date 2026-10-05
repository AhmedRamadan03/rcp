<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();

            $table->text('local_path')->nullable();
            $table->text('cloud_path')->nullable();
            $table->string('storage_driver')->default('local');
            $table->string('bucket_config')->nullable();

            $table->string('source')->nullable();

            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->boolean('synced_to_cloud')->default(false);
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('storage_driver');
            $table->index('synced_to_cloud');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};

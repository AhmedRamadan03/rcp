<?php

use App\Enums\ClientType;
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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'Insurance company',
                'External collection office',
            ])->default(ClientType::INSURANCE_COMPANY);
            $table->string('title');
            $table->text('subtitle');
            $table->text('slug');
            $table->string('email');
            $table->string('phone');
            $table->string('mobile')->nullable();
            $table->text('address')->nullable();
            $table->text('logo')->nullable();
            $table->text('cover_image')->nullable();
            $table->text('website')->nullable();
            $table->text('subscription_key')->nullable();
            $table->timestamps();
            $table->softDeletesTz($column = 'deleted_at', $precision = 0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};

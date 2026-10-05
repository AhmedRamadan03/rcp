<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('file_id')->nullable()->after('id')
                ->constrained('files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['file_id']);
            $table->dropColumn('file_id');
        });
    }
};

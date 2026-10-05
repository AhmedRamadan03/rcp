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
        //        DB::statement('PRAGMA foreign_keys = OFF;');
        Schema::table('claims', function (Blueprint $table) {
            //            $table->integer('return_reason')->change();
        });
        //        DB::statement('PRAGMA foreign_keys = ON;');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            //            $table->text('return_reason')->nullable()->change();
        });
    }
};

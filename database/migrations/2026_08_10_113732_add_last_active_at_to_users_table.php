<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_active_at')->nullable()->index()->after('discount_percentage');
        });

        // backfill from authentication logs for users who have them
        DB::statement("
            UPDATE users
            SET last_active_at = logs.max_login
            FROM (
                SELECT authenticatable_id, MAX(login_at) as max_login
                FROM authentication_log
                WHERE authenticatable_type = ?
                AND login_at IS NOT NULL
                GROUP BY authenticatable_id
            ) as logs
            WHERE users.id = logs.authenticatable_id
        ", [\App\Models\User::class]);

        // fallback to created_at for users without any logs
        DB::statement("
            UPDATE users
            SET last_active_at = created_at
            WHERE last_active_at IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
             $table->dropColumn('last_active_at');
        });
    }
};

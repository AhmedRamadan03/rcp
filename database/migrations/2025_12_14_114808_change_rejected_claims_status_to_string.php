<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1️⃣ حذف constraint بتاع enum (PostgreSQL)
        DB::statement("
            ALTER TABLE rejected_claims
            DROP CONSTRAINT IF EXISTS rejected_claims_status_check
        ");

        // 2️⃣ تحويل العمود إلى string
        Schema::table('rejected_claims', function (Blueprint $table) {
            $table->string('status')->default('not seen')->change();
        });
    }

    public function down(): void
    {
        // رجوع enum تاني (لو احتاجت)
        DB::statement("
            ALTER TABLE rejected_claims
            ADD CONSTRAINT rejected_claims_status_check
            CHECK (status IN ('accepted', 'rejected', 'not seen'))
        ");

        Schema::table('rejected_claims', function (Blueprint $table) {
            $table->enum('status', ['accepted', 'rejected', 'not seen'])
                ->default('not seen')
                ->change();
        });
    }
};


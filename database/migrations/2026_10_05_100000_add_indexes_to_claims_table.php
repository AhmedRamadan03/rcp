<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->index('debtor_nid', 'claims_debtor_nid_index');
            $table->index('client_id', 'claims_client_id_index');
            $table->index('status', 'claims_status_index');
            $table->index('amount', 'claims_amount_index');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropIndex(['claims_debtor_nid_index']);
            $table->dropIndex(['claims_client_id_index']);
            $table->dropIndex(['claims_status_index']);
            $table->dropIndex(['claims_amount_index']);
        });
    }
};
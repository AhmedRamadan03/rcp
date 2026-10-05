<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = DB::table('permissions')->get();

        foreach ($permissions as $record) {
            $updated = [];

            $columns = [
                'claims',
                'claims_files',
                'claim_status',
                'settings',
                'third_parties',
                'users',
                'permissions',
                'role',
                'transactions',
                'reports',
                'payments',
                'accounting',
            ];

            foreach ($columns as $column) {
                $raw = $record->$column;
                $old = json_decode($raw, true);

                // skip لو مش array صح
                if (!is_array($old)) {
                    continue;
                }

                $new = [];
                foreach ($old as $permKey => $val) {
                    if (is_numeric($val)) {
                        $new[$permKey] = [
                            'granted'      => (int) $val,
                        ];
                    } else {
                        $new[$permKey] = $val;
                    }
                }

                $updated[$column] = json_encode($new);
            }

            if (!empty($updated)) {
                DB::table('permissions')
                    ->where('id', $record->id)
                    ->update($updated);
            }
        }
    }

    public function down(): void
    {
        $permissions = DB::table('permissions')->get();

        foreach ($permissions as $record) {
            $updated = [];

            $columns = [
                'claims',
                'claims_files',
                'claim_status',
                'payment_link',
                'reject_claims',
                'settings',
                'third_parties',
                'users',
                'permissions',
                'role'
            ];

            foreach ($columns as $column) {
                $raw = $record->$column;
                $old = json_decode($raw, true);

                // skip لو مش array صح
                if (!is_array($old)) {
                    continue;
                }

                $new = [];
                foreach ($old as $permKey => $val) {
                    // رجّع القيمة القديمة = granted بس
                    if (is_array($val) && isset($val['granted'])) {
                        $new[$permKey] = $val['granted'];
                    } else {
                        $new[$permKey] = $val;
                    }
                }

                $updated[$column] = json_encode($new);
            }

            if (!empty($updated)) {
                DB::table('permissions')
                    ->where('id', $record->id)
                    ->update($updated);
            }
        }
    }
};




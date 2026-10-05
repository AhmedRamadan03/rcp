<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdatePermission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-permission {--dry-run : Show what would change without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add missing permission_mode keys (e.g. with_approve) with default 0 to permissions JSON columns, preserving existing values';

    /**
     * Execute the console command.
     */
   public function handle()
    {
        try {
            $dryRun = $this->option('dry-run');

            // جيب كل الـ permissions اللي عندها with_approve في permission_mode
            $generalPermissions = DB::table('general_permissions')
                ->whereNotNull('permission_mode')
                ->get(['permission', 'path', 'permission_mode'])
                ->groupBy('path');

            // عمل map سريع: permission_key => modes[]
            $modesMap = [];
            foreach ($generalPermissions as $path => $perms) {
                foreach ($perms as $perm) {
                    $modes = json_decode($perm->permission_mode, true) ?? [];
                    $modesMap[$path][$perm->permission] = $modes;
                }
            }

            $permissions = DB::table('permissions')->get();

            $scanned = 0;
            $updatedRows = 0;
            $skippedColumns = 0;

            $runner = function () use ($permissions, $modesMap, $dryRun, &$scanned, &$updatedRows, &$skippedColumns) {
                foreach ($permissions as $record) {
                    $scanned++;
                    $updated = [];

                    $columns = [
                        'claims', 'claims_files', 'claim_status',
                        'settings', 'third_parties', 'users',
                        'permissions', 'role', 'transactions',
                        'reports', 'payments', 'accounting',
                    ];

                    foreach ($columns as $column) {
                        $raw = $record->$column;
                        $old = $this->decodeColumn($raw);

                        if (!is_array($old)) {
                            if ($raw !== null && $raw !== '') {
                                $skippedColumns++;
                                $this->warn("Skipped undecodable value: permissions.id={$record->id} column={$column}");
                            }
                            continue;
                        }

                        $new = [];
                        foreach ($old as $permKey => $val) {
                            if (is_numeric($val)) {
                                // ابدأ بـ granted بس
                                $entry = ['granted' => (int) $val];

                                // لو الصلاحية دي عندها modes، أضفها بقيمة 0 كـ default
                                $modes = $modesMap[$column][$permKey] ?? [];
                                foreach ($modes as $mode) {
                                    $entry[$mode] = 0;
                                }

                                $new[$permKey] = $entry;

                            } elseif (is_array($val)) {
                                // هي جديدة بالفعل، بس تأكد إن المodes الجديدة موجودة
                                $modes = $modesMap[$column][$permKey] ?? [];
                                foreach ($modes as $mode) {
                                    if (!isset($val[$mode])) {
                                        $val[$mode] = 0; // أضف المفتاح لو مش موجود
                                    }
                                }
                                $new[$permKey] = $val;
                            } else {
                                $new[$permKey] = $val;
                            }
                        }

                        // اكتب العمود بس لو فيه تغيير فعلي
                        if ($new !== $old) {
                            $updated[$column] = json_encode($new);

                            if ($dryRun) {
                                $this->line("Would update permissions.id={$record->id} column={$column}");
                            }
                        }
                    }

                    if (!empty($updated)) {
                        $updatedRows++;

                        if (!$dryRun) {
                            DB::table('permissions')
                                ->where('id', $record->id)
                                ->update($updated);
                        }
                    }
                }
            };

            if ($dryRun) {
                $runner();
            } else {
                DB::transaction($runner);
            }

            $verb = $dryRun ? 'would be updated' : 'updated';
            $this->info("Done. Rows scanned: {$scanned}, rows {$verb}: {$updatedRows}, undecodable columns skipped: {$skippedColumns}.");

            return self::SUCCESS;

        } catch (\Throwable $th) {
            $this->error($th->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Decode a permissions JSON column, tolerating double-encoded legacy values
     * (same handling as Permission::ensureArray()).
     */
    private function decodeColumn($raw): ?array
    {
        if (!is_string($raw)) {
            return is_array($raw) ? $raw : null;
        }

        $decoded = json_decode($raw, true);

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : null;
    }
}

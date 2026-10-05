<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\Log;

class DataMigrationFromOld
{
    public static function run()
    {
        \DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 0;'); // Mysql
        //        \DB::connection('pgsql')->statement("SET session_replication_role = 'replica';");// //Postgresql
        self::migrate_table('clients');
        self::migrate_table('migrations');
        self::migrate_table('settings');
        self::migrate_table('third_parties');
        self::migrate_table('cities');
        self::migrate_table('failed_jobs');
        self::migrate_table('close_reasons');
        self::migrate_table('roles');
        //        self::users();
        self::migrate_table('users');
        self::migrate_table('password_reset_tokens');
        self::migrate_table('personal_access_tokens');
        self::migrate_table('permissions');
        self::migrate_table('uploaded_claims_files');
        self::migrate_table('claims');
        self::migrate_table('claim_files');
        self::migrate_table('assignings');
        self::migrate_table('messages');
        self::migrate_table('calls');
        //        self::migrate_table('payments');
        self::payments();
        self::migrate_table('payment_links');
        self::migrate_table('payment_transactions');
        self::migrate_table('rejected_claims');
        self::migrate_table('discounts');
        self::migrate_table('delegations');
        self::migrate_table('comments');
        self::migrate_table('close_claims');
        self::migrate_table('discharges');
        self::migrate_table('reminders');

        self::migrate_table('additional_files');
        self::migrate_table('change_claim_amount_requests');
        self::migrate_table('claim_amount_histories');
        self::migrate_table('claim_status_histories');
        //        self::migrate_table('escalations');
        self::migrate_table('law_department_claim');
        //        self::migrate_table('notifications');
        self::migrate_table('payment_requests');
        self::migrate_table('status_change_requests');
        self::migrate_table('vouchers');
        self::migrate_table('claim_comments');

        \DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS = 1;'); // Mysql

    }

    public static function migrate_table($table_name)
    {
        try {
            $old_clients = \DB::connection('sqlite_new')->table($table_name)->get();

            \DB::connection('mysql')->table($table_name)->delete();
            foreach ($old_clients->all() as $client) {
                \DB::connection('mysql')->table($table_name)->insert((array) $client);
            }
            Log::channel('stderr')->info(ucfirst($table_name).' are seeding to new database!');
        } catch (\Exception $exception) {
            Log::channel('stderr')->info(ucfirst($table_name).' not found');
        }
    }

    //    public static function roles()
    //    {
    //        $old_roles = \DB::connection('sqlite_old')->table("roles")->get();
    //        foreach ($old_roles->all() as $role) {
    //            \DB::connection('sqlite_new')->table("roles")->insert((array) $role);
    //        }
    //        Log::channel('stderr')->info('Roles are seeding to new database!');
    //    }
    //
    public static function users()
    {
        $old_users = \DB::connection('sqlite_new')->table('users')->get();
        $insert_user_laters = [];
        $first_user = \DB::connection('sqlite_new')->table('users')->whereNull('supervisor_id')->first();

        \DB::connection('mysql')->table('users')->insert((array) $first_user);
        foreach ($old_users->all() as $user) {
            if (\DB::connection('mysql')->table('users')->where('id', $user->id)->count() == 0) {
                if (\DB::connection('mysql')->table('users')->orWhere('id', $user->supervisor_id)->count() > 0) {

                    \DB::connection('mysql')->table('users')->insert((array) $user);

                } else {

                    $insert_user_laters[] = $user;
                }
            }
        }

        if (count($insert_user_laters) > 0) {
            return self::users();
        }

        return true;
    }

    public static function payments()
    {
        $old_roles = \DB::connection('sqlite_new')->table('payments')->select('id', 'claim_id', 'amount', 'paid_amount', 'payment_method', 'payment_status', 'due_date', 'created_at', 'updated_at', 'deleted_at')->get();
        foreach ($old_roles->all() as $role) {
            \DB::connection('mysql')->table('payments')->insert((array) $role);
        }
        Log::channel('stderr')->info('Payments are seeding to new database!');
    }
}

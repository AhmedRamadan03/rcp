<?php

namespace Database\Seeders;

use App\Models\CloseReason;
use DB;
use Illuminate\Database\Seeder;
class CloseReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data=[
            ['reason' => ' يوجد تأمين ساري ','created_at' => now(), 'updated_at' => now()],
            ['reason' => 'الرخصة سارية','created_at' => now(), 'updated_at' => now()],
            ['reason' => 'لا يوجد حق رجوع','created_at' => now(), 'updated_at' => now()],
            ['reason' => 'لا يوجد هوية , رقم الجوال لشخص اخر','created_at' => now(), 'updated_at' => now()],
        ];
        //

        DB::table('close_reasons')->insert($data);

        // CloseReason::create([
        //     'reason' => ' يوجد تأمين ساري ',
        // ]);

        // CloseReason::create([
        //     'reason' => 'الرخصة سارية',
        // ]);

        // CloseReason::create([
        //     'reason' => 'لا يوجد حق رجوع',
        // ]);

        // CloseReason::create([
        //     'reason' => 'لا يوجد هوية , رقم الجوال لشخص اخر',
        // ]);
    }
}

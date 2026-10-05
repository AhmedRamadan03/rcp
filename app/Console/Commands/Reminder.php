<?php

namespace App\Console\Commands;

use App\Helpers\EmailSendingHelper;
use App\Models\ClaimComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Mail;

class Reminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!EmailSendingHelper::isCompanyEmailEnabled()) {
            return;
        }

       
        $baseQuery = ClaimComment::where('seen', false)
            ->whereNotNull('reminder_date');

        $reminders = (clone $baseQuery)
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->pluck('total', 'user_id');


      

        foreach ($reminders as $user_id => $counter) {

            if (!EmailSendingHelper::canUserSendEmail($user_id)) {
                continue;
            }

            $email = User::find($user_id)?->email;
            Mail::to($email)->send(new \App\Mail\Reminder($counter));

            $baseQuery->update(['seen' => true]);

        }
        // foreach ($reminders as $key => $counter) {
        //     $now = Carbon::now()->setSecond(0);
        //     $reminder_time = Carbon::parse($reminder->reminder_date);

        //     if ($now->gte($reminder_time)) {
        //         $email = $reminder->claim?->assignees?->where('is_active', 1)->first()->toUser->email;
        //         Mail::to($email)->send(new \App\Mail\Reminder($reminder));
        //         $reminder->seen = true;
        //         $reminder->save();
        //     }
        // }
    }
}

<?php

namespace App\Console\Commands;

use App\Helpers\EmailSendingHelper;
use App\Enums\ClaimStatus;
use App\Models\ClaimComment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Mail;

class LawReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:law-reminder';

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

        $reminders = ClaimComment::with('claim.assignee.toUser')
            ->where('seen', false)
            ->where('reminder_date', '!=', null)
            ->where('is_court_reminder', 1)
            // TODO have to find better way to compare date and time together
            ->whereDate('reminder_date', '<=', Carbon::now()->addDay()->addHours(3)->setSecond(0))
            ->whereTime('reminder_date', '<=', Carbon::now()->addDay()->addHours(3)->setSecond(0))
            ->whereHas('claim', function ($query) {
                $query->where('status', ClaimStatus::LAW_FIRM->value);
            })
            ->get();

        //        dd($reminders);

        foreach ($reminders as $reminder) {
            $assignee = $reminder->claim->assignees->where('is_active', 1)->first()->toUser;

            if (!EmailSendingHelper::canUserSendEmail($assignee->id)) {
                return;
            }

            Mail::to($assignee->email)->send(new \App\Mail\LawReminderMail($reminder));
            $reminder->seen = true;
            $reminder->save();
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\Roles;
use App\Enums\SmsStatus;
use App\Models\Ivr;
use App\Models\User;
use App\Services\SmsService;
use App\ThirdParty\IVR\IvrInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IvrCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ivr-check';

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
        $ivrs = Ivr::where('is_checked', false)->get();
        Auth::setUser(User::where('role_id', Roles::SUPER_ADMIN)->first());
        $smsService = new SmsService;


        foreach ($ivrs as $ivr) {
            if ($ivr->paymentLink?->payment?->claim?->is_msg_skip) {
                $ivr->status = SmsStatus::SKIPPED->value;
                $ivr->is_checked = true;
                $ivr->save();
            } else {
                # code...
                /** @var IvrInterface $ivrClass */
                $ivrClass = $smsService->returnClassName('ivr');
                $response = $ivrClass->check($ivr->callId);

                $ivr->response = $response;
                //            dd($response);
                if (isset($response['recipients'])) {
                    if (Str::lower($response['recipients'][0]['status']) !== 'queued') {
                        $ivr->status = $response['recipients'][0]['status'];
                        $ivr->is_checked = true;
                    } else {
                        $ivr->status = $response['recipients'][0]['status'];
                    }
                } else {
                    $ivr->status = isset($response['status']) ? $response['status'] : $ivr->status;
                }
                $ivr->save();
            }
        }
    }
}

<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('app:send-links')->everyMinute(); // ->daily()->at("10:00")->timezone("Asia/Riyadh");
        $schedule->command('app:default-on-payment-claim')->daily()->at("00:01")->timezone("Asia/Riyadh");
        $schedule->command('app:escalation-command')->daily()->at("00:01")->timezone("Asia/Riyadh"); // ->dailyAt("08:00")->timezone("Asia/Riyadh");//->daily()->at("10:00")->timezone("Asia/Riyadh");
        $schedule->command('app:sent-supervisor-request-after-one-month')->daily()->at("00:01")->timezone("Asia/Riyadh"); // ->dailyAt("08:00")->timezone("Asia/Riyadh");//->daily()->at("10:00")->timezone("Asia/Riyadh");
        $schedule->command('app:law-reminder')->everyMinute(); // ->dailyAt("08:00")->timezone("Asia/Riyadh");//->daily()->at("10:00")->timezone("Asia/Riyadh");
        $schedule->command('app:reminder')->everyMinute(); // ->dailyAt("08:00")->timezone("Asia/Riyadh");//->daily()->at("10:00")->timezone("Asia/Riyadh");
        $schedule->command('app:transaction-enquiry')->daily()->at('08:00')->timezone('Asia/Riyadh'); // ->dailyAt("08:00")//->daily()->at("10:00")->timezone("Asia/Riyadh");
        $schedule->command('ivr-check')->everyFiveMinutes(); // ->dailyAt("08:00")//->daily()->at("10:00")->timezone("Asia/Riyadh");
        $schedule->command('check-elm-message')->everyFiveMinutes(); // ->dailyAt("08:00")//->daily()->at("10:00")->timezone("Asia/Riyadh");
        $schedule->command('app:close-target')->everyFiveMinutes();
        $schedule->command('app:delegation-expiration')->dailyAt('00:01')->timezone('Asia/Riyadh');// daily at 1 minute past midnight (Riyadh time)
        $schedule->command('app:renew-targets')->monthlyOn(1, '00:05')->timezone('Asia/Riyadh');
        $schedule->command('tokens:record-expired-logouts')->hourly(); // note : this command should run before sanctum:prune-expired command
        $schedule->command('sanctum:prune-expired', ['--hours' => 24])->daily();

        //        $schedule->command('app:default-payment-full-payment-to-follow-up')->everyMinute(); //->dailyAt("08:00")//->daily()->at("10:00")->timezone("Asia/Riyadh");
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

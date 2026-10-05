<?php

namespace App\Console\Commands;

use App\Services\PendingActionService;
use Exception;
use Illuminate\Console\Command;

class SentSupervisorRequestAfterOneMonth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sent-supervisor-request-after-one-month';

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
        //
        try {
            $pending_service = new PendingActionService;
            $pending_claims = $pending_service->getClaimsToBeSentToSupervisor();
            foreach ($pending_claims as $claim) {
                $pending_service->create($claim);
            }

            $this->info('There are '.$pending_claims->count().' pending claims sent to supervisor.');
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }

    }
}

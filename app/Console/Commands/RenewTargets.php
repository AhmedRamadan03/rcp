<?php

namespace App\Console\Commands;

use App\Services\TargetService;
use Illuminate\Console\Command;

class RenewTargets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:renew-targets';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew monthly targets automatically';

    /**
     * Execute the console command.
     */
    public function handle()
    {
         app(TargetService::class)->renewReoccurringTargets();
        $this->info('Targets renewed successfully!');
        return 0;
    }
}

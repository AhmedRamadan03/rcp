<?php

namespace App\Console\Commands;

use App\Enums\ThirdPartyType;
use App\Models\CustomTemplate;
use App\Models\ThirdParty;
use Illuminate\Console\Command;

class FillCustomTemplateThirdParty extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fill-custom-template-third-party';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Attach each client\'s active third party to existing custom templates missing one.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $clientIds = CustomTemplate::whereNull('third_party_id')
            ->select('client_id')
            ->distinct()
            ->pluck('client_id');

        foreach ($clientIds as $clientId) {
            $activeThirdParty = ThirdParty::where('client_id', $clientId)
                ->where('type', ThirdPartyType::SMS->value)
                ->where('is_active', true)
                ->first();

            if (!$activeThirdParty) {
                $this->warn("No active SMS third party for client {$clientId}, skipping.");
                continue;
            }

            $updated = CustomTemplate::where('client_id', $clientId)
                ->whereNull('third_party_id')
                ->update(['third_party_id' => $activeThirdParty->id]);

            $this->info("Client {$clientId}: attached third_party #{$activeThirdParty->id} to {$updated} templates.");
        }

        return self::SUCCESS;
    }
}

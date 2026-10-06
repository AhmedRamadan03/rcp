<?php
namespace App\Jobs;

use App\Models\Claim;
use App\Services\Risk\RiskCalculationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateClaimRiskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(
        protected Claim $claim,
        protected ?int $calculatedBy = null,
        protected string $trigger = 'updated'
    ) {}

    public function handle(RiskCalculationService $service): void
    {
        $claim = $this->claim->fresh();
        if (!$claim) {
            return;
        }

        $service->calculate(
            $claim,
            \App\Models\User::find($this->calculatedBy),
            $this->trigger
        );
    }
}
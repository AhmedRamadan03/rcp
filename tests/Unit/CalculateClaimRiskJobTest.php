<?php

namespace Tests\Unit;

use App\Jobs\CalculateClaimRiskJob;
use App\Models\Claim;
use App\Services\Risk\RiskCalculationService;
use Mockery;
use Tests\TestCase;

class CalculateClaimRiskJobTest extends TestCase
{
    public function test_it_passes_the_trigger_to_the_risk_service(): void
    {
        $claim = Mockery::mock(Claim::class);
        $freshClaim = Mockery::mock(Claim::class);
        $service = Mockery::mock(RiskCalculationService::class);

        $claim->shouldReceive('fresh')->once()->andReturn($freshClaim);
        $service->shouldReceive('calculate')
            ->once()
            ->with($freshClaim, null, 'manual_recalc')
            ->andReturn([]);

        $job = new CalculateClaimRiskJob($claim, null, 'manual_recalc');

        $job->handle($service);
    }
}

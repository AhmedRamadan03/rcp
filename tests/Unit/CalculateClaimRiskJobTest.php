<?php

namespace Tests\Unit;

use App\Jobs\CalculateClaimRiskJob;
use App\Models\Claim;
use App\Models\User;
use App\Services\Risk\RiskCalculationService;
use Mockery;
use Tests\TestCase;

class CalculateClaimRiskJobTest extends TestCase
{
    public function test_it_passes_the_trigger_to_the_risk_service(): void
    {
        $claim = Mockery::mock(Claim::class);
        $freshClaim = Mockery::mock(Claim::class);
        $user = Mockery::mock(User::class);
        $service = Mockery::mock(RiskCalculationService::class);

        $claim->shouldReceive('fresh')->once()->andReturn($freshClaim);
        $service->shouldReceive('calculate')
            ->once()
            ->with($freshClaim, $user, 'manual_recalc')
            ->andReturn([]);

        $job = new CalculateClaimRiskJob($claim, 123, 'manual_recalc');

        $this->app->instance(User::class, $user);
        $service->shouldReceive('calculate')->once();

        // Resolve the user lookup without touching the database.
        $this->partialMock(User::class, function ($mock) use ($user) {
            $mock->shouldReceive('find')->with(123)->andReturn($user);
        });

        $job->handle($service);
    }
}

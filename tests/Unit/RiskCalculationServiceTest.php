<?php

namespace Tests\Unit;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\City;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use App\Services\Risk\RiskCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RiskCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['title' => 'Test Role']);
        Client::factory()->create();
        User::factory()->create();
    }

    public function test_city_risk_is_neutral_without_a_city(): void
    {
        $claim = $this->createClaim(['city_id' => null]);

        $result = app(RiskCalculationService::class)->calculate($claim);

        $this->assertSame(30, $result['risk_factors']['city_risk']['score']);
        $this->assertSame(3, $result['risk_factors']['city_risk']['contribution']);
    }

    public function test_city_risk_is_neutral_when_city_has_fewer_than_1000_historical_claims(): void
    {
        $city = City::factory()->create();
        $this->createClaim(['city_id' => $city->id]);

        $historicalClaims = Claim::factory()
            ->count(999)
            ->state(['city_id' => $city->id])
            ->create();

        $result = app(RiskCalculationService::class)->calculate($historicalClaims->first());

        $this->assertSame(30, $result['risk_factors']['city_risk']['score']);
    }

    public function test_city_risk_uses_rejected_cancelled_and_closed_claims_after_1000_claims(): void
    {
        $city = City::factory()->create();

        $claims = Claim::factory()
            ->count(1000)
            ->state(['city_id' => $city->id, 'status' => ClaimStatus::REGISTERED->value])
            ->make();

        for ($i = 0; $i < 100; $i++) {
            $claims[$i]->status = ClaimStatus::REJECTED->value;
            $claims[$i + 100]->status = ClaimStatus::CLOSED->value;
            $claims[$i + 200]->status = 'cancelled';
        }

        DB::table('claims')->insert($claims->map(fn (Claim $claim) => $claim->getAttributes())->all());

        $currentClaim = $this->createClaim(['city_id' => $city->id]);

        $result = app(RiskCalculationService::class)->calculate($currentClaim);

        $this->assertSame(30, $result['risk_factors']['city_risk']['score']);
        $this->assertSame(3, $result['risk_factors']['city_risk']['contribution']);
    }

    private function createClaim(array $attributes = []): Claim
    {
        return Claim::factory()->create($attributes);
    }
}

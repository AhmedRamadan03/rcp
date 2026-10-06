<?php

namespace Tests\Unit;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\City;
use App\Models\Client;
use App\Models\User;
use App\Services\Risk\RiskCalculationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RiskCalculationServiceTest extends TestCase
{
    protected ?int $clientId = null;
    protected ?int $userId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientId = Client::query()->value('id');
        $this->userId = User::query()->value('id');

        $this->assertNotNull($this->clientId, 'Risk tests require at least one client in the test database.');
        $this->assertNotNull($this->userId, 'Risk tests require at least one user in the test database.');
    }

    public function test_city_risk_is_neutral_without_a_city(): void
    {
        $claim = $this->createClaim(['city_id' => null]);

        $result = app(RiskCalculationService::class)->calculate($claim);

        $this->assertSame(30, $result['risk_factors']['city_risk']['score']);
        $this->assertSame(3.0, $result['risk_factors']['city_risk']['contribution']);
    }

    public function test_city_risk_is_neutral_when_city_has_fewer_than_1000_historical_claims(): void
    {
        $city = City::factory()->create();

        Claim::factory()
            ->count(999)
            ->state($this->claimState($city->id))
            ->create();

        $currentClaim = $this->createClaim([
            'city_id' => $city->id,
        ]);

        $result = app(RiskCalculationService::class)->calculate($currentClaim);

        $this->assertSame(30, $result['risk_factors']['city_risk']['score']);
        $this->assertSame(3.0, $result['risk_factors']['city_risk']['contribution']);
    }

    public function test_city_risk_uses_rejected_cancelled_and_closed_claims_after_1000_claims(): void
    {
        $city = City::factory()->create();

        $claims = Claim::factory()
            ->count(1000)
            ->state($this->claimState($city->id, [
                'status' => ClaimStatus::REGISTERED->value,
            ]))
            ->make();

        for ($i = 0; $i < 100; $i++) {
            $claims[$i]->status = ClaimStatus::REJECTED->value;
            $claims[$i + 100]->status = ClaimStatus::CLOSED->value;
            $claims[$i + 200]->status = 'cancelled';
        }

        DB::table('claims')->insert(
            $claims->map(fn (Claim $claim) => $claim->getAttributes())->all()
        );

        $currentClaim = $this->createClaim([
            'city_id' => $city->id,
        ]);

        $result = app(RiskCalculationService::class)->calculate($currentClaim);

        $this->assertSame(30, $result['risk_factors']['city_risk']['score']);
        $this->assertSame(3.0, $result['risk_factors']['city_risk']['contribution']);
    }

    private function createClaim(array $attributes = []): Claim
    {
        return Claim::factory()->create($this->claimState(null, $attributes));
    }

    private function claimState(?int $cityId, array $attributes = []): array
    {
        return array_merge([
            'client_id' => $this->clientId,
            'user_id' => $this->userId,
            'city_id' => $cityId,
            'debtor_nid' => 'risk-test-' . uniqid(),
        ], $attributes);
    }
}

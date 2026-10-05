<?php

namespace Tests\Unit;

use App\Models\Claim;
use Tests\TestCase;

class ClaimRiskAttributesTest extends TestCase
{
    public function test_risk_level_color_and_label_are_derived_from_score(): void
    {
        $claim = new Claim();

        $claim->risk_score = 75;

        $this->assertSame('high', $claim->risk_level);
        $this->assertSame('red', $claim->risk_color);
        $this->assertSame('مخاطر عالية - مراجعة إجبارية', $claim->risk_label);

        $claim->risk_score = 50;

        $this->assertSame('medium', $claim->risk_level);
        $this->assertSame('orange', $claim->risk_color);

        $claim->risk_score = 10;

        $this->assertSame('low', $claim->risk_level);
        $this->assertSame('green', $claim->risk_color);
    }
}

<?php

namespace Tests\Unit;

use App\Enums\RiskDecision;
use Tests\TestCase;

class RiskDecisionTest extends TestCase
{
    public function test_low_risk_is_auto_process(): void
    {
        $this->assertSame(RiskDecision::AUTO_PROCESS, RiskDecision::fromScore(29.99));
    }

    public function test_medium_risk_requires_supervisor_review(): void
    {
        $this->assertSame(RiskDecision::SUPERVISOR_REVIEW, RiskDecision::fromScore(30));
        $this->assertSame(RiskDecision::SUPERVISOR_REVIEW, RiskDecision::fromScore(69.99));
    }

    public function test_high_risk_requires_mandatory_review(): void
    {
        $this->assertSame(RiskDecision::MANDATORY_REVIEW, RiskDecision::fromScore(70));
        $this->assertSame(RiskDecision::MANDATORY_REVIEW, RiskDecision::fromScore(100));
    }

    public function test_decision_labels_are_localized(): void
    {
        $this->assertSame('معالجة عادية', RiskDecision::AUTO_PROCESS->label());
        $this->assertSame('مراجعة المشرف', RiskDecision::SUPERVISOR_REVIEW->label());
        $this->assertSame('مراجعة إجبارية', RiskDecision::MANDATORY_REVIEW->label());
    }
}

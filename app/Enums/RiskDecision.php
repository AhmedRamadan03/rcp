<?php

namespace App\Enums;

enum RiskDecision: string
{
    case AUTO_PROCESS = 'auto_process';
    case SUPERVISOR_REVIEW = 'supervisor_review';
    case MANDATORY_REVIEW = 'mandatory_review';

    public static function fromScore(float $score): self
    {
        return match (true) {
            $score >= 70 => self::MANDATORY_REVIEW,
            $score >= 30 => self::SUPERVISOR_REVIEW,
            default => self::AUTO_PROCESS,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AUTO_PROCESS => 'معالجة عادية',
            self::SUPERVISOR_REVIEW => 'مراجعة المشرف',
            self::MANDATORY_REVIEW => 'مراجعة إجبارية',
        };
    }
}

<?php
namespace App\Services\Risk;

use App\Enums\ClaimType;
use App\Enums\DebtorType;
use App\Models\Claim;
use App\Models\ClaimRiskHistory;
use App\Models\User;
use Carbon\Carbon;

class RiskCalculationService
{
    protected array $weights = [
        'claim_type' => 15,
        'amount' => 20,
        'accident_age_months' => 15,
        'claim_history_count' => 15,
        'debtor_type' => 20,
        'city_risk' => 10,
        'client_risk' => 5,
    ];

    public function calculate(Claim $claim, ?User $calculatedBy = null, string $trigger = 'updated'): array
    {
        $factors = [];
        $score = 0;

        $typeScore = $this->calculateTypeScore($claim);
        $factors['claim_type'] = ['score' => $typeScore, 'weight' => $this->weights['claim_type'], 'label' => 'نوع المطالبة'];
        $score += $typeScore * $this->weights['claim_type'] / 100;

        $amountScore = $this->calculateAmountScore($claim);
        $factors['amount'] = ['score' => $amountScore, 'weight' => $this->weights['amount'], 'label' => 'قيمة المطالبة'];
        $score += $amountScore * $this->weights['amount'] / 100;

        $ageScore = $this->calculateAccidentAgeScore($claim);
        $factors['accident_age'] = ['score' => $ageScore, 'weight' => $this->weights['accident_age_months'], 'label' => 'قدم الحادث'];
        $score += $ageScore * $this->weights['accident_age_months'] / 100;

        $historyScore = $this->calculateHistoryScore($claim);
        $factors['claim_history'] = ['score' => $historyScore, 'weight' => $this->weights['claim_history_count'], 'label' => 'السجل السابق'];
        $score += $historyScore * $this->weights['claim_history_count'] / 100;

        $debtorScore = $this->calculateDebtorScore($claim);
        $factors['debtor_type'] = ['score' => $debtorScore, 'weight' => $this->weights['debtor_type'], 'label' => 'نوع المدين'];
        $score += $debtorScore * $this->weights['debtor_type'] / 100;

        $cityScore = $this->calculateCityRiskScore($claim);
        $factors['city_risk'] = ['score' => $cityScore, 'weight' => $this->weights['city_risk'], 'label' => 'مخاطر المنطقة'];
        $score += $cityScore * $this->weights['city_risk'] / 100;

        $clientScore = $this->calculateClientRiskScore($claim);
        $factors['client_risk'] = ['score' => $clientScore, 'weight' => $this->weights['client_risk'], 'label' => 'مخاطر العميل'];
        $score += $clientScore * $this->weights['client_risk'] / 100;

        $finalScore = min(100, round($score, 2));
        $level = $this->getLevel($finalScore);

        $result = [
            'risk_score' => $finalScore,
            'risk_level' => $level,
            'risk_factors' => $factors,
            'calculated_at' => now(),
        ];

        $claim->update([
            'risk_score' => $finalScore,
            'risk_factors' => $factors,
            'risk_calculated_at' => now(),
        ]);

        ClaimRiskHistory::create([
            'claim_id' => $claim->id,
            'risk_score' => $finalScore,
            'risk_factors' => $factors,
            'trigger' => $trigger,
            'calculated_by' => $calculatedBy?->id,
        ]);

        return $result;
    }

    private function calculateTypeScore(Claim $claim): int
    {
        return match($claim->type) {
            ClaimType::INDIVIDUAL => 20,
            ClaimType::CORPORATE => 40,
            ClaimType::LEASING => 35,
            ClaimType::GOVERNMENT => 25,
            ClaimType::MUNICIPALITY => 30,
            default => 10,
        };
    }

    private function calculateAmountScore(Claim $claim): int
    {
        $amount = $claim->amount;
        if ($amount >= 100000) return 100;
        if ($amount >= 50000) return 80;
        if ($amount >= 20000) return 60;
        if ($amount >= 10000) return 40;
        if ($amount >= 5000) return 25;
        return 10;
    }

    private function calculateAccidentAgeScore(Claim $claim): int
    {
        if (!$claim->accident_date) return 50;

        $accidentDate = Carbon::parse($claim->accident_date);
        if ($accidentDate->isFuture()) return 50;

        $months = $accidentDate->diffInMonths(now());
        if ($months > 24) return 90;
        if ($months > 12) return 70;
        if ($months > 6) return 40;
        if ($months > 3) return 20;
        return 10;
    }

    private function calculateHistoryScore(Claim $claim): int
    {
        if (!$claim->debtor_nid) return 0;

        $stats = Claim::query()
            ->where('debtor_nid', $claim->debtor_nid)
            ->where('id', '!=', $claim->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status IN ('rejected', 'cancelled', 'closed', 'manaual_collected') THEN 1 ELSE 0 END) as rejected")
            ->first();

        $previous = (int) ($stats->total ?? 0);
        $rejected = (int) ($stats->rejected ?? 0);

        return min(100, ($previous * 10) + ($rejected * 20));
    }

    private function calculateDebtorScore(Claim $claim): int
    {
        return match($claim->debtor_type) {
            DebtorType::THIRD_PARTY => 80,
            DebtorType::INSURED => 20,
            default => 50,
        };
    }

    private function calculateCityRiskScore(Claim $claim): int
    {
        return 30;
    }

    private function calculateClientRiskScore(Claim $claim): int
    {
        if (!$claim->client_id) return 30;

        $stats = Claim::query()
            ->where('client_id', $claim->client_id)
            ->where('id', '!=', $claim->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status IN ('rejected', 'cancelled') THEN 1 ELSE 0 END) as rejected")
            ->first();

        $total = (int) ($stats->total ?? 0);
        $rejected = (int) ($stats->rejected ?? 0);

        if ($total === 0) return 30;

        return min(100, ($rejected / $total) * 100 * 2);
    }

    private function getLevel(float $score): string
    {
        return match(true) {
            $score >= 70 => 'high',
            $score >= 30 => 'medium',
            default => 'low',
        };
    }
}
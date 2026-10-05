<?php
namespace App\Http\Controllers\Api\V1;

use App\Models\Claim;
use App\Services\Risk\RiskCalculationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RiskController extends Controller
{
    public function __construct(protected RiskCalculationService $riskService) {}

    public function show($id)
    {
        $claim = Claim::with('riskHistories.calculatedBy')->visibleTo(auth()->user())->findOrFail($id);
     

        return response()->json([
            'success' => true,
            'message' => 'تم جلب بيانات المخاطر',
            'data' => [
                'risk_score' => $claim->risk_score ?? 0,
                'risk_level' => $claim->risk_level ?? 'low',
                'risk_color' => $claim->risk_color ?? 'green',
                'risk_label' => $claim->risk_label ?? 'مخاطر منخفضة',
                'risk_factors' => $claim->risk_factors ?? [],
                'calculated_at' => $claim->risk_calculated_at,
                'history' => $claim->riskHistories->map(fn($h) => [
                    'id' => $h->id,
                    'score' => $h->risk_score,
                    'trigger' => $h->trigger,
                    'created_at' => $h->created_at,
                    'calculated_by' => $h->calculatedBy?->name,
                ]),
            ]
        ]);
    }

    public function recalculate(int $claimId)
    {
        $claim = Claim::visibleTo(auth()->user())->findOrFail($claimId);
        $result = $this->riskService->calculate($claim, auth()->user(), 'manual_recalc');
        $claim->refresh();

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة حساب المخاطر بنجاح',
            'data' => $result,
        ]);
    }

    public function summary(Request $request)
    {
        $user = auth()->user();
        $stats = Claim::visibleTo($user)
            ->selectRaw('COUNT(*) as total_claims')
            ->selectRaw('SUM(CASE WHEN risk_score >= 70 THEN 1 ELSE 0 END) as high_risk')
            ->selectRaw('SUM(CASE WHEN risk_score >= 30 AND risk_score < 70 THEN 1 ELSE 0 END) as medium_risk')
            ->selectRaw('SUM(CASE WHEN risk_score < 30 THEN 1 ELSE 0 END) as low_risk')
            ->selectRaw('COALESCE(AVG(risk_score), 0) as avg_score')
            ->first();

        $totalClaims = (int) ($stats->total_claims ?? 0);
        $highRisk = (int) ($stats->high_risk ?? 0);

        return response()->json([
            'success' => true,
            'message' => 'ملخص المخاطر',
            'data' => [
                'total_claims' => $totalClaims,
                'high_risk' => $highRisk,
                'medium_risk' => (int) ($stats->medium_risk ?? 0),
                'low_risk' => (int) ($stats->low_risk ?? 0),
                'avg_score' => round((float) ($stats->avg_score ?? 0), 2),
                'high_risk_percentage' => $totalClaims > 0 ? round(($highRisk / $totalClaims) * 100, 2) : 0,
            ]
        ]);
    }
}
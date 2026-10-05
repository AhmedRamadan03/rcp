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
        $claims = Claim::visibleTo($user);

        return response()->json([
            'success' => true,
            'message' => 'ملخص المخاطر',
            'data' => [
                'total_claims' => $claims->count(),
                'high_risk' => (clone $claims)->highRisk()->count(),
                'medium_risk' => (clone $claims)->mediumRisk()->count(),
                'low_risk' => (clone $claims)->lowRisk()->count(),
                'avg_score' => round((clone $claims)->avg('risk_score') ?? 0, 2),
                'high_risk_percentage' => $claims->count() > 0 ? round(((clone $claims)->highRisk()->count() / $claims->count()) * 100, 2) : 0,
            ]
        ]);
    }
}
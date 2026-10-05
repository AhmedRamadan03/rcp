<?php

namespace App\Http\Controllers\Api\claims;

use App\Enums\Roles;
use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\PaymentLink;
use App\Enums\ClaimStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class ClaimsChartData extends Controller{
    public function index(Request $request){
        $claims = Claim::all();
        // count(dd($claims));
        $total_claims = $claims->count();
        // $pending_claims = $claims->where('status', operator: 'pending')->count();
            $approved_claims = $claims->where('status', 'approved')->count();
            $approved_amount = $claims->where('status', 'approved')->sum('amount');
        $rejected_claims = $claims->where('status', 'rejected')->count();
        $rejected_amount = $claims->where('status', 'rejected')->sum('amount');
        $rigstered_claims = $claims->where('status', 'registered')->count();
        $registered_amount = $claims->where('status', 'registered')->sum('amount');
        $transformed_to_lawyer_claims = $claims->where('status', 'transformed_to_lawyer')->count();
        $transformed_to_lawyer_amount = $claims->where('status', 'transformed_to_lawyer')->sum('amount');
        $transformed_to_elm = $claims->where('status', 'transfer_to_elm')->count();
        $transformed_to_elm_amount = $claims->where('status', 'transfer_to_elm')->sum('amount');
        //follow_up manaual_collected follow_up/partial default_in_payment_by_system default_in_payment
        $total_claims = $claims->groupBy('status')->count();
        $total_amount = $claims->sum('amount');
        $closed_claims = $claims->where('is_close', 1)->count();
        $closed_amount = $claims->where('is_close', 1)->sum('amount');
        $collected_claims = PaymentLink::where('send_status', 'paid')
        ->where('type', 'installment_payment')
        ->selectRaw("sum(amount) as amount_sum, count(*) as count")->first();

        // Get filter parameters
        $startDate = $request->input('start_date', now()->subMonths(12)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $type = $request->input('type', 'all'); // all, collected, delayed, full_payment

        // Get monthly collected amounts with filters
        $monthly_collected = PaymentLink::where('send_status', 'paid')
            ->when($type !== 'all', function($query) use ($type) {
                return $query->where('type', $type);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(amount) as total_amount,
                COUNT(*) as count
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Format the monthly data for the chart
        $monthly_data = [
            'labels' => $monthly_collected->pluck('month'),
            'amounts' => $monthly_collected->pluck('total_amount'),
            'counts' => $monthly_collected->pluck('count'),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'type' => $type
            ]
        ];

        $delayed_claims = PaymentLink::where('type', 'delay_payment')
        ->selectRaw("sum(amount) as amount_sum, count(*) as count")->first();
        $full_payment_claims = PaymentLink::where('send_status', 'paid')
        ->where('type', 'full_payment')
        ->selectRaw("sum(amount) as amount_sum, count(*) as count")->first();

        $partial_collected_claims = $claims->whereIn('status', [
            ClaimStatus::FOLLOW_UP->value,
            ClaimStatus::PARTIAL->value
        ])->sum("amount");
    
        $default_in_payment_claims = $claims->whereIn('status', [
            ClaimStatus::DEFAULT_IN_PAYMENT->value,
            ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value
        ])->sum("amount");


        $all_calims_amount = $claims->sum('amount');

        $follow_up_amount = $claims->whereIn('status', [
            ClaimStatus::FOLLOW_UP->value,
            // ClaimStatus::PARTIAL->value
        ])->sum("amount");
        
        // $partial_collected_claims = $parties->sum('parties');

        $super_admin_users = User::where('role_id', Roles::SUPER_ADMIN->value)->count();
        $super_visor_users = User::where('role_id', Roles::SUPERVISOR->value)->count();
        $collector_users = User::where('role_id', Roles::COLLECTOR->value)->count();
        $external_office_users = User::where('role_id', Roles::EXTERNAL_OFFICE->value)->count();
        $legal_department_users = User::where('role_id', Roles::LEGAL_DEPARTMENT->value)->count();
        $taheiya_admin_users = User::where('role_id', Roles::TAHEIYA_ADMIN->value)->count();
        $others_users = User::where('role_id', Roles::OTHERS->value)->count();

        // $all_users = $super_admin_users + $super_visor_users + $collector_users + $external_office_users + $legal_department_users + $taheiya_admin_users + $others_users;

        
        return response()->json([
            // 'all_claims' => $claims,
            'total_claims' => $total_claims,
            'approved_claims' => $approved_claims,
            'approved_amount' => $approved_amount,
            'rejected_claims' => $rejected_claims,
            'rejected_amount' => $rejected_amount,
            'rigstered_claims' => $rigstered_claims,
            'registered_amount' => $registered_amount,
            'transformed_to_lawyer_claims' => $transformed_to_lawyer_claims,
            'transformed_to_lawyer_amount' => $transformed_to_lawyer_amount,
            'transformed_to_elm' => $transformed_to_elm,
            'transformed_to_elm_amount' => $transformed_to_elm_amount,
            'total_amount' => $total_amount,
            'closed_claims' => $closed_claims,
            'closed_amount' => $closed_amount,
            'collected_claims' => $collected_claims,
            'monthly_collected' => $monthly_data,
            'delayed_claims' => $delayed_claims,
            'full_payment_claims' => $full_payment_claims,
            'partial_collected_claims' => $partial_collected_claims,
            'default_in_payment_claims' => $default_in_payment_claims,
            'follow_up_amount' => $follow_up_amount,
            'super_admin_users' => $super_admin_users,
            'super_visor_users' => $super_visor_users,
            'collector_users' => $collector_users,
            'external_office_users' => $external_office_users,
            'legal_department_users' => $legal_department_users,
            'taheiya_admin_users' => $taheiya_admin_users,
            'others_users' => $others_users,
        ]);
    
        // return view('livewire.dashboard', [
        //     'total_claims' => $total_claims,
        //     'approved_claims' => $approved_claims,
        //     'rejected_claims' => $rejected_claims,
        //     'rigstered_claims' => $rigstered_claims,
        //     'transformed_to_lawyer_claims' => $transformed_to_lawyer_claims,
        //     'transformed_to_elm' => $transformed_to_elm,
        //     'total_amount' => $total_amount,
        //     'closed_claims' => $closed_claims,
        //     'collected_claims' => $collected_claims,
        //     'delayed_claims' => $delayed_claims,
        //     'full_payment_claims' => $full_payment_claims,
        //     // 'total_claims' => $total_claims,
        // ]);


        
    }
}
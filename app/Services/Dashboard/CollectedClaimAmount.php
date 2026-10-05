<?php

namespace App\Services\Dashboard;

use App\Http\Resources\Api\V1\Dashboard\AssignedClaimResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class CollectedClaimAmount extends AssignedClaimService
{
    protected function getByYear(string $entity, array $entityIds)
    {
        $years = DB::table('payment_transactions')
            ->select(DB::raw('DISTINCT EXTRACT(YEAR FROM created_at) as year'))
            ->orderBy('year', 'asc')
            ->pluck('year');
     
        $data = [];
        foreach ($years as $year) {
            $data[$year] = $this->getTopDataBasedOnEntity($entity, $entityIds, $year);
        }
        return response()->json(['success' => true, 'data' => $data]);
    }

    protected function getTopUsers(array $userIds, int $year = null, int $month = null, string $day = null)
    {
        $query = DB::table('users')
        ->join('payment_links', 'users.id', '=', 'payment_links.collector_id')
        ->join('payment_transactions', 'payment_links.id', '=', 'payment_transactions.link_id')
        ->where('payment_transactions.send_status', 'paid')
        ->where('payment_links.send_status', 'paid')
        ->select('users.id', 'users.name', DB::raw('SUM(payment_transactions.amount) as total'));

        if (!empty($userIds)) {
            $query->whereIn('users.id', $userIds);
        }

        if ($year)  $query->whereYear('payment_transactions.created_at', $year);
        if ($month) $query->whereMonth('payment_transactions.created_at', $month);
        if ($day)   $query->whereDate('payment_transactions.created_at', $day);
        return AssignedClaimResource::collection(
            $query->groupBy('users.id', 'users.name')
                ->orderByDesc('total')
                ->limit(4)
                ->get()
        );
    }

    protected function getTopRoles(array $roleIds, int $year = null, int $month = null, string $day = null): JsonResource
    {
        $query = DB::table('roles')
            ->select('roles.id', DB::raw('roles.title as name'), DB::raw('SUM(payment_transactions.amount) as total'))
            ->join('users', 'users.role_id', '=', 'roles.id')
            ->join('payment_links', 'users.id', '=', 'payment_links.collector_id')
            ->join('payment_transactions', 'payment_links.id', '=', 'payment_transactions.link_id')
            ->where('payment_links.send_status', 'paid');

        if (!empty($roleIds)) {
            $query->whereIn('roles.id', $roleIds);
        }

        if ($year)  $query->whereYear('payment_transactions.created_at', $year);
        if ($month) $query->whereMonth('payment_transactions.created_at', $month);
        if ($day)   $query->whereDate('payment_transactions.created_at', $day);

        return AssignedClaimResource::collection(
            $query->groupBy('roles.id', 'roles.title')
                ->orderByDesc('total')
                ->limit(4)
                ->get()
        );
    }
}
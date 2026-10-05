<?php
namespace App\Services\Dashboard;

use App\Http\Resources\Api\V1\Dashboard\AssignedClaimResource;
use Carbon\Carbon;
use DatePeriod;
use DateInterval;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class AssignedClaimService
{
    public function getAssignedClaims(string $entity, string $type, array $userIds = [], string $date = null)
    {
        switch ($type) {
            case 'year':
                return $this->getByYear($entity, $userIds);

            case 'month':
                $year = $date ? Carbon::parse($date)->year : now()->year;
                return $this->getByMonth($entity, $userIds, $year);

            case 'day':
                $day = $date ?: now()->format('Y-m-d');
                return $this->getByDay($entity, $userIds, $day);

            default:
                return response()->json(['message' => 'Invalid type'], 422);
        }
    }

    // -------------------------
    // Type = year
    // -------------------------
    protected function getByYear(string $entity, array $entityIds)
    {
        $years = DB::table('assignings')
            ->select(DB::raw('DISTINCT EXTRACT(YEAR FROM assign_date) as year'))
            ->orderBy('year', 'asc')
            ->pluck('year');

        $data = [];
        foreach ($years as $year) {
            $data[$year] = $this->getTopDataBasedOnEntity($entity, $entityIds, $year);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    // -------------------------
    // Type = month
    // -------------------------
    protected function getByMonth(string $entity, array $entityIds, int $year)
    {
        $data = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthName = date("F", mktime(0, 0, 0, $month, 1));
            $data[$monthName] = $this->getTopDataBasedOnEntity($entity, $entityIds, $year, $month);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    // -------------------------
    // Type = day (week)
    // -------------------------
   protected function getByDay(string $entity, array $entityIds, string $selectedDay)
{
    $data = [];
    $dayCarbon = Carbon::parse($selectedDay);

    $weekStart = $dayCarbon->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
    $weekEnd   = $dayCarbon->copy()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');

    $period = new DatePeriod(
        Carbon::parse($weekStart),
        new DateInterval('P1D'),
        Carbon::parse($weekEnd)->addDay()
    );

    foreach ($period as $day) {
        $dayName = $day->format('l');

        $data[$dayName] = $this->getTopDataBasedOnEntity($entity, $entityIds, null, null, $day->format('Y-m-d'));
    }

    return response()->json(['success' => true, 'data' => $data]);
}


    // -------------------------
    // دالة مساعدة تجيب top 4 users حسب الفلتر
    // -------------------------
    protected function getTopUsers(array $userIds, int $year = null, int $month = null, string $day = null)
    {
        $query = DB::table('users')
            ->select('users.id', 'users.name', DB::raw('COUNT(assignings.id) as total'))
            ->join('assignings', 'users.id', '=', 'assignings.to')
            ->where('assignings.is_active', 1);

        if (!empty($userIds)) $query->whereIn('users.id', $userIds);

        if ($year)  $query->whereRaw('EXTRACT(YEAR FROM assignings.assign_date) = ?', [$year]);
        if ($month) $query->whereRaw('EXTRACT(MONTH FROM assignings.assign_date) = ?', [$month]);
        if ($day)   $query->whereDate('assignings.assign_date', $day);

        return AssignedClaimResource::collection(
            $query->groupBy('users.id', 'users.name')
                  ->orderByDesc('total')
                  ->limit(4)
                  ->get()
        );
    }

    /*
     * getStats by Role
     * we use the same function getTopUsers() with different conditions
     */
    protected function getTopRoles(array $roleIds, int $year = null, int $month = null, string $day = null): JsonResource
    {
        $query = DB::table('roles')
            ->select('roles.id', DB::raw('roles.title as name'), DB::raw('COUNT(assignings.id) as total'))
            ->join('users', 'users.role_id', '=', 'roles.id')
            ->join('assignings', 'users.id', '=', 'assignings.to')
            ->where('assignings.is_active', 1);

        if (!empty($roleIds)) $query->whereIn('roles.id', $roleIds);

        if ($year)  $query->whereRaw('EXTRACT(YEAR FROM assignings.assign_date) = ?', [$year]);
        if ($month) $query->whereRaw('EXTRACT(MONTH FROM assignings.assign_date) = ?', [$month]);
        if ($day)   $query->whereDate('assignings.assign_date', $day);

        return AssignedClaimResource::collection(
            $query->groupBy('roles.id', 'roles.title')
                ->orderByDesc('total')
                ->limit(4)
                ->get()
        );
    }

    protected function getTopDataBasedOnEntity(string $entity, array $entityIds, int $year = null, int $month = null, string $day = null)
    {
        return match ($entity) {
            'role' => $this->getTopRoles($entityIds, $year, $month, $day),
            default => $this->getTopUsers($entityIds, $year, $month, $day),
        };
    }
}

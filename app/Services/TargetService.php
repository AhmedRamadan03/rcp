<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\Roles;
use App\Enums\TargetStatus;
use App\Enums\TargetType;
use App\Exceptions\NotAuthorizeException;
use App\Exceptions\TargetAlreadyExistsException;
use App\Http\Resources\UserCurrentTargetResource;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Models\Target;
use App\Models\TargetHistory;
use App\Models\TargetPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TargetService
{
    /**
     * @return Collection<User>
     */
    public function getCurrentTarget(): Collection
    {
        if (auth()->user()->role_id == Roles::SUPER_ADMIN->value) {
            return User::with(['currentTarget', 'previousTarget'])
                ->whereIn('role_id', [Roles::EXTERNAL_OFFICE->value, Roles::COLLECTOR->value])
                ->get();
        } else {
            return User::with(['currentTarget', 'previousTarget'])
                ->whereIn('role_id', [Roles::EXTERNAL_OFFICE->value, Roles::COLLECTOR->value])
                ->when(in_array(auth()->user()->role_id, [Roles::SUPERVISOR->value, Roles::SUPER_ADMIN->value, Roles::SENIOR_COLLECTOR->value]), function ($query) {
                    $query->where('supervisor_id', auth()->user()->id);
                })->get();
        }
    }

    /**
     * @return Target
     *
     * @throws \Exception
     */
    public function createTarget(array $data)
    {
        if ($this->userHasTarget($data['userId'])) {
            throw new TargetAlreadyExistsException(__('target.target_already_exist'));
        }
        try {
            return DB::transaction(function () use ($data) {
                $startDate = $this->getDate($data['target_type'])['start_date'];
                $endDate = $this->getDate($data['target_type'])['end_date'];

                $linkIds = PaymentLink::where('collector_id', $data['userId'])->pluck('id')->toArray();
                $totalClaim = PaymentTransaction::whereIn('link_id', $linkIds)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where('send_status', 'paid')->sum('amount');

                $target = Target::create([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'target' => $data['target'],
                    'user_id' => $data['userId'],
                    'achieved' => $totalClaim,
                    'remaining' => $data['target'] - $totalClaim >= 0 ? $data['target'] - $totalClaim : 0,
                    'is_reoccurring' => $data['is_reoccurring'],
                    'target_type' => $data['target_type'],
                    // 'period_id' => $period->id,
                ]);
                if ($target->achieved >= $target->target && ! $target->is_completed) {
                    $target->is_completed = true;
                    $target->status = TargetStatus::ACHIEVED->value;
                    $target->save();
                }

                $this->writeTargetHistory($target->id, $data['userId'], $data['target'], $data['target'], 'add', auth()->user()->id);

                NotificationService::create([
                    'title_ar' => __('notification.add_target', [], 'ar'),
                    'title_en' => __('notification.add_target', [], 'en'),
                    'description_ar' => __('notification.add_target_for_user', [], 'ar'),
                    'description_en' => __('notification.add_target_for_user', [], 'en'),
                    'regarding' => NotificationType::GENERAL->value,
                    'user_id' => $data['userId'],
                    'to' => json_encode([$data['userId']]),
                    'links' => json_encode([
                        [
                            'title_ar' => ' تارجت جديد',
                            'title_en' => ' New target ',
                            'url' => 'target-management/' . $data['userId'],
                        ],
                    ]),
                ]);
                return $target;
            });
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    private function getDate($type)
    {
        switch ($type) {
            case TargetType::MONTHLY->value:
                return $this->getMonthlyDate();
            case TargetType::ANNUALLY->value:
                return $this->getYearDate();
            case TargetType::QUARTERLY->value:
                return $this->getQuarterYear();
            case TargetType::SEMI_ANNUALLY->value:
                return $this->getHalfYear();
        }
    }
    private function getMonthlyDate()
    {
        $date = [];
        $date['start_date'] = Carbon::today()->firstOfMonth()->startOfDay();
        $date['end_date'] = Carbon::today()->endOfMonth()->endOfDay();
        return $date;
    }
    private function getYearDate()
    {
        $date = [];
        $date['start_date'] = Carbon::today()->startOfYear()->startOfDay();
        $date['end_date'] = Carbon::today()->endOfYear()->endOfDay();

        return $date;
    }
    private function getQuarterYear()
    {
        $date = [];

        $date['start_date'] = Carbon::today()->firstOfQuarter()->startOfDay();
        $date['end_date']   = Carbon::today()->lastOfQuarter()->endOfDay();

        return $date;
    }
    private function getHalfYear()
    {
        $now = Carbon::today();
        $date = [];

        if ($now->month <= 6) { // النصف الأول
            $date['start_date'] = $now->copy()->startOfYear()->startOfDay(); // Jan 1 00:00:00
            $date['end_date']   = $now->copy()->startOfYear()->addMonths(5)->endOfMonth()->endOfDay(); // Jun 30 23:59:59
        } else { // النصف الثاني
            $date['start_date'] = $now->copy()->startOfYear()->addMonths(6)->startOfMonth()->startOfDay(); // Jul 1 00:00:00
            $date['end_date']   = $now->copy()->endOfYear()->endOfDay(); // Dec 31 23:59:59
        }

        return $date;
    }




    public function createPeriod(array $data)
    {
        $startDate = Carbon::parse($data['startDate'])->format('Y-m-d');
        $endDate = Carbon::parse($data['endDate'])->format('Y-m-d');

        // Period is collision with another period
        $periodIsCollied = TargetPeriod::where('start_date', '=', $startDate)
            ->orWhere('end_date', '=', $endDate)
            ->count() > 0;

        if ($periodIsCollied) {
            throw new \Exception(__('target.period_already_exist'));
        }
        $isActive = false;
        if (Carbon::today()->eq($startDate)) {
            $isActive = true;

            TargetPeriod::where('is_active', true)->update(['is_active' => false]);
        }

        return TargetPeriod::create([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => $isActive,
        ]);
    }

    public function getActivePeriod()
    {
        return TargetPeriod::where('is_active', true)->first();
    }

    /**
     * @return Target
     *
     * @throws \Exception
     */
    public function editTarget(array $data)
    {
        try {
            $target = Target::findOrFail($data['targetId']);

            if (auth()->user()->role_id == Roles::SENIOR_COLLECTOR->value) {
                if (!(new UserService())->belongToSupervisor($target->user_id, auth()->user()->id)) {
                    throw new NotAuthorizeException('this target is not under your supervision');
                }
            }

            // if (Carbon::today()->firstOfMonth()->gt(Carbon::parse($target->start_date))) {
            //     throw new NotAuthorizeException(__('target.target_is_expired'));
            // }

            $newRemainning = $data['target'] - $target->achieved > 0 ? $data['target'] - $target->achieved : 0;
            $oldTarget = $target->target;
            if ($oldTarget == $data['target']) {
                throw new NotAuthorizeException(__('target.target_does_not_change'));
            }

            if ($newRemainning != 0) {
                $data['status'] = TargetStatus::PENDING->value;
            }

            $target->update([
                'target' => $data['target'],
                'remaining' => $newRemainning,
                'status' => $data['status'] ?? $target->status,
            ]);

            $this->writeTargetHistory($target->id, $data['userId'], $oldTarget, $data['target'], 'edit', auth()->user()->id);

            if ($newRemainning == 0) {
                // TODO Send Completion Notification
                $target->update([
                    'is_completed' => 1,
                    'status' => TargetStatus::ACHIEVED->value,
                ]);
            }

            return $target;
        } catch (NotAuthorizeException $e) {
            throw new \Exception($e->getMessage());
        } catch (\Exception $exception) {

            throw new \Exception(__('target.target_does_not_exist') . ' - ' . $exception->getMessage());
        }
    }

    public function userHasTarget($userId): bool
    {
        $today = now()->toDateString();

        return Target::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->exists();
    }


    public function addToAchieveTarget(int $userId, int $achieveAmount)
    {
        if (! $this->userHasTarget($userId)) {
            throw new \Exception(__('target.collector_has_no_target'));
        }
        // $activePeriod = $this->getActivePeriod();
        // $target = Target::with('user')
        //     ->where('user_id', $userId)
        //     ->where('start_date', $activePeriod->start_date)
        //     ->first();
        $todayStart = now()->startOfDay(); // 2026-03-31 00:00:00
        $todayEnd = now()->endOfDay();     // 2026-03-31 23:59:59

        $target = Target::where('user_id', $userId)
            ->where('start_date', '<=', $todayEnd)
            ->where('end_date', '>=', $todayStart)
            ->first();
        $target->achieved = $target->achieved + $achieveAmount;
        $target->remaining = $target->remaining - $achieveAmount >= 0 ? $target->remaining - $achieveAmount : 0;

        if ($target->achieved >= $target->target && ! $target->is_completed) {
            $target->is_completed = true;
            $target->status = TargetStatus::ACHIEVED->value;
            // TODO Notificion for complete goal
        }

        $target->save();

        return $target;
    }

    public function removeFromAchieveTarget(int $userId, int $achieveAmount)
    {
        if (! $this->userHasTarget($userId)) {
            throw new \Exception(__('target.collector_has_no_target'));
        }
        $target = Target::where('user_id', $userId)
            ->where('start_date', Carbon::today()->firstOfMonth()->format('Y-m-d'))
            ->first();

        $target->achieved = $target->achieved - $achieveAmount;
        $target->remaining = $target->remaining + $achieveAmount;

        if ($target->achieved < $target->target && $target->is_completed) {
            $target->is_completed = false;
            $target->status = TargetStatus::PENDING->value;
            // TODO Notificion for complete goal
        }

        $target->save();

        return $target;
    }

    public function getUserCurrentTarget(int $userId)
    {
        try {
            $activePeriod = $this->getActivePeriod();
            $target = Target::where('user_id', $userId)
                ->where('start_date', $activePeriod->start_date)
                ->whereHas('user', function ($query) {
                    $query->whereIn('role_id', [Roles::EXTERNAL_OFFICE->value, Roles::COLLECTOR->value]);
                })
                ->firstOrFail();

            return UserCurrentTargetResource::make($target)->response()->getData();
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @return Collection
     */
    public function getOfficerTargetHistory(int $userId, ?int $perPage = null, $isQuery = false, array $filters= [])
    {
        $query =  Target::select(['id', 'target', 'achieved', 'remaining', 'start_date', 'end_date', 'status', 'deleted_at'])
            ->withTrashed()
            ->where('user_id', $userId)
            ->orderByDesc('start_date');

        $query = $this->filterQuery($query, $filters);

        if ($perPage) {
            return $query->paginate($perPage);
        }

        if ($isQuery) {
            return $query;
        }

        return $query->get();
    }

    public function getTargetHistory()
    {
        return TargetHistory::with(['user', 'target'])->orderByDesc('created_at')->get();
    }

    public function writeTargetHistory($targetId, $userId, $previousTarget, $currentTarget, $type, $authorId)
    {
        TargetHistory::create([
            'target_id' => $targetId,
            'user_id' => $userId,
            'previous_target' => $previousTarget,
            'current_target' => $currentTarget,
            'type' => $type,
            'author_id' => $authorId,
        ]);
    }

    public function deleteTarget(Target $target): bool
    {
        if (auth()->user()->role_id == Roles::SENIOR_COLLECTOR->value) {
            if (!(new UserService())->belongToSupervisor($target->user_id, auth()->user()->id)) {
                throw new \Exception('this target is not under your supervision');
            }
        }
        return $target->delete();
    }



    public function renewReoccurringTargets()
    {
        $today = now()->toDateString();

        // كل التارجتات اللي انتهت ولازالت متكررة
        $targets = Target::where('end_date', '<', $today)
            ->where('is_reoccurring', true)
            ->get();

        foreach ($targets as $target) {

            // احسب الفترة القادمة حسب نوعها
            $newPeriod = $this->calculateNextPeriod(
                $target->start_date,
                $target->end_date
            );
            $linkIds = PaymentLink::where('collector_id', $target->user_id)->pluck('id')->toArray();
            $totalClaim = PaymentTransaction::whereIn('link_id', $linkIds)
                ->whereBetween('created_at', [$newPeriod['start'], $newPeriod['end']])
                ->where('send_status', 'paid')->sum('amount');
            $newTarget = Target::create([
                'user_id'   => $target->user_id,
                'target'    => $target->target,
                'achieved'  => $totalClaim,
                'remaining' => $target->target - $totalClaim,
                'start_date' => $newPeriod['start'],
                'end_date'  => $newPeriod['end'],
                'target_type' => $target->target_type,
                'is_reoccurring' => true,
            ]);

            $this->writeTargetHistory($newTarget->id, $newTarget->user_id, $newTarget->target, $newTarget->target, 'add', null);
        }
    }

    public function calculateNextPeriod($start, $end)
    {
        $start = Carbon::parse($start)->startOfDay();
        $end   = Carbon::parse($end)->endOfDay();


        // Monthly
        if ($end->diffInDays($start) <= 31) {
            return [
                'start' => $start->copy()->addMonth()->startOfMonth()->startOfDay(),
                'end'   => $start->copy()->addMonth()->endOfMonth()->endOfDay(),
            ];
        }

        // Quarterly (3 months)
        if ($end->diffInMonths($start) == 2) {

            $newStart = $start->copy()->addMonths(3)->firstOfQuarter();
            return [
                'start' => $newStart->startOfDay()->toDateTimeString(),
                'end'   => $newStart->copy()->lastOfQuarter()->endOfDay()->toDateTimeString(),
            ];
        }

        // Half year (6 months)
        if ($end->diffInMonths($start) == 5) {
            $newStart = $start->copy()->addMonths(6);
            return [
                'start' => $newStart->startOfMonth()->startOfDay()->toDateTimeString(),
                'end'   => $newStart->copy()->addMonths(5)->endOfMonth()->endOfDay()->toDateTimeString(),
            ];
        }

        // Yearly
        return [
            'start' => $start->copy()->addYear()->startOfYear()->startOfDay()->toDateTimeString(),
            'end'   => $start->copy()->addYear()->endOfYear()->endOfDay()->toDateTimeString(),
        ];
    }


     private function searchFilter($queryBuilder, $search)
{
    $queryBuilder->where(function ($q) use ($search) {
        $q->whereRaw('CAST(target AS TEXT) LIKE ?', ["%{$search}%"])
            ->orWhereRaw('CAST(achieved AS TEXT) LIKE ?', ["%{$search}%"])
            ->orWhereRaw('CAST(remaining AS TEXT) LIKE ?', ["%{$search}%"]);
    });
}
    

    private function filterQuery($targets,$filters)
    {
       

        if (isset($filters['target'])) {
            $targets->where('target', 'like',"%{$filters['target']}%");
        }

        if (isset($filters['arcieved_target'])) {
            $targets->where('achieved', 'like', "%{$filters['arcieved_target']}%");
            
        }
      
        if (isset($filters['remaining_target'])) {
            $targets->where('remaining', 'like',"%{$filters['remaining_target']}%"); 
        }
        if (isset($filters['start_date'])) {
            $targets->whereDate('start_date', $filters['start_date']); 
        }
        if (isset($filters['end_date'])) {
            $targets->whereDate('end_date', $filters['end_date']); 
        }
        if (isset($filters['status'])) {
            $targets->whereIn('status', Arr::Wrap($filters['status'])); 
        }
        if (isset($filters['ids'])) {
            $targets->whereIn('id', Arr::Wrap($filters['ids'])); 
        }

        if (isset($filters['search'])) {
            $this->searchFilter($targets, $filters['search']);
        }

        if (isset($filters['start_date_from']) && isset($filters['start_date_to'])) {
            $targets->whereBetween('start_date', [$filters['start_date_from'], $filters['start_date_to']]);
        }
        if (isset($filters['end_date_from']) && isset($filters['end_date_to'])) {
            $targets->whereBetween('end_date', [$filters['end_date_from'], $filters['end_date_to']]);
        }
        if (isset($filters['created_at_from']) && isset($filters['created_at_to'])) {
            $targets->whereBetween('created_at', [$filters['created_at_from'], $filters['created_at_to']]);
        }
       return $targets;
    }
}
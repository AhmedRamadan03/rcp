<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Enums\Roles;
use App\Mail\NewUserMail;
use App\Models\Assigning;
use App\Models\Claim;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserAutoAssign;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Arr;

class UserService
{
    public function create($user)
    {
        $user['password'] = Hash::make('password');
        $user['client_id'] = Auth::user()->client_id;
        $user['first_time_login'] = true;
        $user['discount_percentage'] = $user['user_discount_percentage'] ?? null;

        $requestUser = $user;
        $user = User::create($user)->refresh();

        $permissions = [
            'user_id' => $user->id,
            'role_id' => $user->role_id,
        ];

        $permissions = array_merge($permissions, default_permission($user->role_id));
        Permission::create(

            $permissions
        );

        //send email to user
        Mail::to($user->email)->send(new NewUserMail($user));


        return $user;
    }

    public function list()
    {
        return User::with(
            'role',
            'permission',
        )
            ->latest()
            ->get();
    }

    public function info($user_id)
    {
        return User::with(
            'client',
            'role',
            'permission',
            'autoAssigned')
            ->where('id', $user_id)->get()->first();
    }

    public function active($user_id)
    {
        $user = User::findOrFail($user_id);
        $user->is_active = 1;

        return $user->save();
    }

    public function disActive($user_id)
    {
        $user = User::find($user_id);
        $user->is_active = 0;

        return $user->save();
    }

    //    public function autoAssigned($user_id , $request) {
    //        $user = UserAutoAssign::firstOrCreate(['user_id' => $user_id]);
    //        $assigned = false;
    //        if (isset($request['auto_assign'])) {
    //            $assigned = true;
    //        }
    //        $user->is_auto_assigned = $request['auto_assign'];
    //        $user->save();
    //        return $this->info($user_id);
    //
    //    }

    public function getSupervisors()
    {
        return User::where('client_id', Auth::user()->client_id)
            ->whereNotIn('role_id', [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value])
            ->get();
    }

    public function edit(User $user, $request)
    {
        try {

            //        $user["password"] = Hash::make("password");
            $request['discount_percentage'] = $request['user_discount_percentage'] ?? null;
            $request['client_id'] = Auth::user()->client_id;

            //        UserAutoAssign::create([
            //            "user_id" => $user->id,
            //            "is_auto_assigned" => false
            //        ]);
            $user->fill($request)->save();

            // the logic after this comment overwrite the default permission
            // if ($user->permission() != null) {
            //     $user->permission()->delete();
            // }

            // $permissions = [
            //     'user_id' => $user->id,
            //     'role_id' => $user->role_id,
            // ]; // default_per()

            // $permissions = array_merge($permissions, default_permission($user->role_id));
            // Permission::create(

            //     $permissions
            // );

            return $user;
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    public function delete(User $user, int $new_assined_user_id)
    {
        try {
            
            $claims_id = $this->getActiveClaimAssiendToUser($user);
            $this->reAssign($user, $new_assined_user_id, $claims_id);
            
            (new DelegationService())->deactivateUserDelegations($user);

            $user->delete();

            return true;
        } catch (Exception  $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    public function getActiveClaimAssiendToUser(User $user)
    {
        // $this->status === ClaimStatus::CLOSED->value || $this->status === ClaimStatus::DONE->value || $this->status === ClaimStatus::REJECTED->value || $this->status === ClaimStatus::MANUAL_COLLECTED->value || $this->status === ClaimStatus::BANK_COLLECTED->value || $this->status === ClaimStatus::CREDITED->value
        return Claim::whereHas('assignees', function ($query) use ($user) {
            $query->where('is_active', 1)
                ->where('to', $user->id);
        })
            ->whereNotIn('status', [
                ClaimStatus::REJECTED->value,
                ClaimStatus::CLOSED->value,
                ClaimStatus::DONE->value,
                ClaimStatus::MANUAL_COLLECTED->value,
                ClaimStatus::BANK_COLLECTED->value,
                ClaimStatus::CREDITED->value,
            ])
            ->pluck('id')
            ->toArray();

    }

    public function reAssign(User $from_user, int $to_user, array $claims_id)
    {
        try {
            Assigning::where('is_active', 1)
                ->where('to', $from_user->id)
                ->whereIn('claim_id', $claims_id)
                ->update([
                    'to' => $to_user,
                ]);

            return true;
        } catch (Exception  $exception) {
            throw new Exception($exception->getMessage());
        }
    }

    public function getTargetCollectorsForSupervisor($user_id)
    {
        $user = User::findOrFail($user_id);

        if (in_array(auth()->user()->role_id??$user->role_id ,[Roles::SUPERVISOR->value, Roles::SUPER_ADMIN->value])) {
            return User::query()->whereIn('role_id', [
                Roles::COLLECTOR->value,
                Roles::EXTERNAL_OFFICE->value
            ]);
        }elseif(in_array(auth()->user()->role_id??$user->role_id ,[Roles::SENIOR_COLLECTOR->value])){

            return User::query()->where('supervisor_id', $user->id);
        }else{
            return User::query()->where('id', $user->id);
        }

    }

    public function getCollectorsForSupervisor($user_id)
    {
        $user = User::findOrFail($user_id);

        if (auth()->user()->role_id == Roles::SUPER_ADMIN->value) {
            return User::whereIn('role_id', [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value])->get();
        } else {
            return User::where('supervisor_id', $user->id)->get();
        }
    }

    public function belongToSupervisor($collector_ids, $supervisor_id)
    {
        $ids = array_unique(Arr::wrap($collector_ids));

        if(empty($ids)){
            return false;
        }

        return User::whereIn('id', $ids)
                ->where('supervisor_id', $supervisor_id)
                ->count() === count($ids);
    }

    public function loginToTechSupport()
    {
        return TechSupport::loginIfExists(auth()->user()->toArray());
    }
}

<?php

namespace App\Policies;

use App\Models\Claim;
use App\Models\User;

class ClaimPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
//     public function view(User $user, $claim_id)
//     {

//         try {
//             $claim = Claim::find($claim_id);
//             if (!$claim) {
//                 abort(response()->json([
//                     'success' => false,
//                     'message' => 'Claim not found'
//                 ], 404));

//             }
//             dd($claim);
//             return can_user_access_claim($claim);
//         } catch (\Exception $exception) {
//                 dd(args: $exception->getMessage());
//             return false;
//         }

// //        return can_user_access_claim($claim);
//     }

    public function view(User $user, $claim_id)
    {
        $claim = Claim::find($claim_id);

        if (!$claim) {
            abort(response()->json([
                'success' => false,
                'message' => 'Claim not found'
            ], 404));
        }

        if (!custom_policy($user, safe_json_decode($user->permission->first()->claims)->show_claim == 1)) {
            return false;
        }

        // If claim not found OR user cannot access
        if (!$claim || !can_user_access_claim($claim)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Not Authorized to Access This Claim'
            ], 403));
        }

        return true;
    }


    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->create_claim == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, $claim_id)
    {
        try {
            $claim = Claim::find($claim_id);
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->update_claim == 1) && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }

    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Claim $claim)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->delete_claim == 1) && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Claim $claim): bool {}

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Claim $claim): bool {}

    public function approve_claim(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->approve_claim == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function reject(User $user)
    {
        try {
            return true;//custom_policy($user, json_decode($user->permission->first()->claims)->create_reject_claims == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function upload(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims_files)->create_claims_files == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function assign_claim(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->assign_claim == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }
    
    public function update_billed(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->update_claim == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function manage_client_requests(User $user, Claim $claim)
    {
        try {
            return custom_policy(
                $user,
                safe_json_decode($user->permission->first()->claims)->manage_client_requests == 1
            );
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function generate_discharge_letter(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->generate_discharge_letter == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function send_voucher_letter(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->send_voucher_letter == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function generate_voucher_letter(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->generate_voucher_letter == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function send_discharge_letter(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->send_discharge_letter == 1);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function generate_voucher(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->generate_discharge_letter == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function cancel(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claim_status)->canceled == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }
    public function transfer_to_legal(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claim_status)->transfer_to_legal == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }
    public function back_claim_to_original_status(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claim_status)->back_claim_to_original_status == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }
    public function default_in_payment_manual(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claim_status)->default_in_payment_manual == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }
    public function collected_credited(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claim_status)->collected_credited == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }
    public function collected_out_of_system(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claim_status)->collected_out_of_system == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }
    public function close_claim(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claim_status)->close_claim == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }
    public function dispute_payment(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->dispute_payment == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }
    public function update_claim_amount(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->update_claim_amount == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function import(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->import == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function export(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->claims)->export == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function showReports(User $user)
    {
        try {
            return custom_policy($user, safe_json_decode($user->permission->first()->reports)->show_reports == 1); // && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }

    }

    public function showClaimFiles(User $user, $claim_id)
    {
        try {
            $claim = Claim::find($claim_id);
            return custom_policy($user, safe_json_decode($user->permission->first()->claims_files)->show_claims_files == 1) && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function deleteClaimFile(User $user, $claim_id)
    {
        try {
            $claim = Claim::find($claim_id);
            return custom_policy($user, safe_json_decode($user->permission->first()->claims_files)->delete_claims_files == 1) && can_user_access_claim($claim);
        } catch (\Exception $exception) {
            return false;
        }
    }

}
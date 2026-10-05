<?php

namespace App\Policies;

use App\Enums\Roles;
use App\Models\StatusChangeRequest;
use App\Models\User;

class StatusChangeRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, $requestId): bool
    {
        $statusChangeRequest = StatusChangeRequest::find($requestId);
        if (!$statusChangeRequest) {
            $this->abortNotFound();
        }

        if ($statusChangeRequest->user_id == $user->id || $statusChangeRequest->supervisor_id == $user->id || in_array($user->id, $statusChangeRequest->approvalUserIds()) ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, $requestId): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, $requestId): bool
    {
        return false;
    }

    /**
     * Determine whether the user can accept the request.
     */
    public function accept(User $user, $requestId): bool
    {
        $statusChangeRequest = StatusChangeRequest::find($requestId);
        if (!$statusChangeRequest) {
            $this->abortNotFound();
        }


        if ($statusChangeRequest->supervisor_id == $user->id || $user->role_id == Roles::SUPER_ADMIN->value) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can reject the request.
     */
    public function reject(User $user, $requestId): bool
    {
        $statusChangeRequest = StatusChangeRequest::find($requestId);
        if (!$statusChangeRequest) {
            $this->abortNotFound();
        }


        if ($statusChangeRequest->supervisor_id == $user->id || $user->role_id == Roles::SUPER_ADMIN->value) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can cancel the request.
     */
    public function cancel(User $user, $requestId): bool
    {
        $statusChangeRequest = StatusChangeRequest::find($requestId);
        if (!$statusChangeRequest) {
            $this->abortNotFound();
        }

        if ($statusChangeRequest->user_id == $user->id || $statusChangeRequest->supervisor_id == $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can escalate the request.
     */
    public function escalate(User $user, $requestId): bool
    {
    }

    private function abortNotFound()
    {
        abort(response()->json([
            'success' => false,
            'message' => 'Status Change Request not found'
        ],404));
    }

}

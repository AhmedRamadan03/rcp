<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Models\Role;
use App\Enums\Roles;
use App\Enums\UserType;
use App\Events\General\UpdateUserEvent;
use App\Services\PermissionService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserEditController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected PermissionService $permissionService
    ) {
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Normalize email before validation
        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim($request->email))]);
        }
        if ($request->filled('phone')) {
            try {
                $phone = new \Propaganistas\LaravelPhone\PhoneNumber(trim($request->phone));
                if ($phone->isValid()) {
                    $request->merge(['phone' => $phone->formatE164()]);
                }
            } catch (\Propaganistas\LaravelPhone\Exceptions\NumberParseException) {
                // Leave as-is; the `phone` rule below will reject it with a proper message.
            }
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email:filter', Rule::unique('users')->ignore($user->id)],
            'phone' => ['sometimes', 'string', 'max:20', Rule::unique('users')->ignore($user->id), 'phone:INTERNATIONAL'],
            'role_id' => ['sometimes', 'exists:roles,id'],
            'user_discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'password' => 'nullable|string|min:8|confirmed',
            'user_type' => ['required', Rule::in([UserType::INTERNAL_USER->value, UserType::EXTERNAL_USER->value])],
            'supervisor_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->whereIn('role_id', [
                        Roles::SUPERVISOR->value,
                        Roles::SUPER_ADMIN->value,
                        Roles::SENIOR_COLLECTOR->value
                    ])
                    ->whereNot('id', $id),
            ],
        ], [
            'phone.phone' => 'Please enter a valid phone number including the country code.',
        ]);

        try {
            DB::beginTransaction();

            $userData = $validated;

            // Update password if provided
            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            } else {
                unset($userData['password']);
            }

            // Update user
            $user = $this->userService->edit($user, $userData);
            event(new UpdateUserEvent());
            // Handle permissions if provided

            DB::commit();

            return response()->json([
                "succes" => true,
                'message' => 'User updated successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "succes" => false,
                'message' => 'Failed to update user: ' . $e->getMessage(),
            ], 500);
        }
    }
}

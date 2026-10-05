<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Enums\Roles;
use App\Enums\UserType;
use App\Models\Role;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;

class UserCreateController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {
    }

    public function store(Request $request)
    {

        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim($request->email))]);
        }

        if ($request->filled('phone')) {
            try {
                $phone = new PhoneNumber(trim($request->phone));
                if ($phone->isValid()) {
                    $request->merge(['phone' => $phone->formatE164()]);
                }
            } catch (\Propaganistas\LaravelPhone\Exceptions\NumberParseException) {
                // Leave as-is; the `phone` rule below will reject it with a proper message.
            }
        }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email:filter|unique:users,email',
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone', 'phone:INTERNATIONAL'],
            'role_id' => ['required', 'exists:roles,id'],
            'user_type' => ['required', Rule::in([UserType::INTERNAL_USER->value, UserType::EXTERNAL_USER->value])],
            'user_discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'supervisor_id' => [
                'nullable',
                Rule::exists('users', 'id')->whereIn('role_id', [Roles::SUPERVISOR->value, Roles::SUPER_ADMIN->value, Roles::SENIOR_COLLECTOR->value]),
            ],
        ], [
            'phone.phone' => 'Please enter a valid phone number including the country code',
        ]);

        try {
            DB::beginTransaction();


            $userData = array_merge($validated, [
                'email_verified_at' => now(),
            ]);

            $user = $this->userService->create($userData);

            // Handle avatar upload if present
            if ($request->hasFile('avatar')) {
                $user->addMediaFromRequest('avatar')
                    ->toMediaCollection('avatars');
            }

            DB::commit();

            return response()->json([
                "succes" => true,
                'message' => 'User created successfully',
                'data' => new JsonResource($user->load('role'))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "succes" => false,
                'message' => 'Failed to create user: ' . $e->getMessage(),
            ], 500);
        }
    }
}

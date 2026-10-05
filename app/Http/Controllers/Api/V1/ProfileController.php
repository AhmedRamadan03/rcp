<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AuthRegister;
use App\Enums\AccountSettings;
use App\Enums\CloudStorageDisk;
use App\Enums\EmailSending;
use App\Enums\FileSource;
use App\Enums\TwoFactorAuthentication;
use App\Enums\UserType;
use App\Events\General\UpdateUserEvent;
use App\Http\Resources\Api\V1\Dashboard\UserTargetResource;
use App\Http\Resources\Api\V1\Users\ProfileUserResource;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\FilesUploader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Exception;

class ProfileController extends BaseAPIController
{
    public function show()
    {

        $user = auth()->user();
        // if (is_null($user)) {
        //     return $this->errorResponse('User profile not found', 404);
        // }

        return $this->successResponse($user);
    }
    public function getInfo()
    {

        $user = auth()->user();
        return $this->successResponse(new UserTargetResource($user));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

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
            'name' => 'sometimes|string|max:255|unique:users,name,' . $user->id,
            'email' => 'sometimes|string|email:filter|max:255|unique:users,email,' . $user->id,
            'phone' => ['sometimes', 'string', 'max:255', 'unique:users,phone,' . $user->id, 'phone:INTERNATIONAL'],
            'address' => 'sometimes|string|max:255',
            'bio' => 'nullable|string',
            'current_password' => 'required_with:password|string',
            'password' => 'sometimes|nullable|string|min:6|confirmed',
        ], [
            'phone.phone' => 'Please enter a valid phone number including the country code.',
        ]);

        try {

            if ($request->has('password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return $this->errorResponse('Current password is not correct', 422);
                }
                $validated['password'] = Hash::make($request->password);
            }

            // Remove current_password from validated data as it's not needed for update
            unset($validated['current_password']);

            $user->update($validated);
            event(new UpdateUserEvent());

            // Revoke all Sanctum tokens (logs out user from all devices) if password was changed
            if ($request->filled('password')) {
                (new AuthRegister())->logoutAllSessions($user);
                $user->tokens()->delete();

                return $this->successResponse($user, 'User profile has been updated successfully. Please login again with your new password.');
            }

            return $this->successResponse($user, 'User profile has been updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update user profile', 500, [$e->getMessage()]);
        }
    }


    public function storeProfileImage(Request $request)
    {

        try {
            $validProfileImage = $request->validate([
                'profile_image' => 'required|image|mimes:jpeg,png,jpg,svg|max:2048',
            ]);

            $client_dir = strtolower(config('app.client_name'));

            $user = auth()->user();

            $uploader = new FilesUploader();

            if ($user->profileImage) {
                $uploader->delete($user->profileImage);
                $user->profile_image = null;
                $user->profile_image_file_id = null;
            } elseif ($user->profile_image) {
                $uploader->delete($user->profile_image);
            }

            $file = $uploader->upload($validProfileImage['profile_image'], [
                'source'      => FileSource::PROFILE_IMAGES,
                'directory'   => $client_dir . '/profiles/' . $user->id,
                'uploaded_by' => $user->id,
                'disk'        => CloudStorageDisk::GCS_PUBLIC->value,
            ]);

            $user->profile_image          = $file->local_path ?? $file->cloud_path;
            $user->profile_image_file_id  = $file->id;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile image updated successfully',
                'data' => new ProfileUserResource($user)
            ]);


        }catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ],400);
        }
       }
    public function deleteProfileImage(Request $request)
    {
        try {

            $user = auth()->user();

            $uploader = new FilesUploader();

            if ($user->profileImage) {
                $uploader->delete($user->profileImage);
            } elseif ($user->profile_image) {
                $uploader->delete($user->profile_image);
            }

            $user->profile_image         = null;
            $user->profile_image_file_id = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile image deleted successfully',
                'data' => new ProfileUserResource($user)
            ]);


        }catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile image',
            ],400);
        }
    }

    public function updateProfileSetting(Request $request)
    {
        try {
            $validSettingKeys = array_column(AccountSettings::cases(), 'value');

            $validated = $request->validate([
                'key' => ['required', 'string', Rule::in($validSettingKeys)],
                'value' => ['required', 'integer'],
            ]);

            $settingValueMap = [
                AccountSettings::TWO_FACTOR_AUTH->value => TwoFactorAuthentication::class,
                AccountSettings::EMAIL_SENDING->value => EmailSending::class,
            ];

            $enumClass = $settingValueMap[$validated['key']];
            $allowedValues = array_column($enumClass::cases(), 'value');

            if (!in_array($validated['value'], $allowedValues, strict: true)) {
                throw ValidationException::withMessages([
                    'value' => ['The selected value is invalid for the given setting key.'],
                ]);
            }

            $setting = UserSetting::firstOrCreate(
                ['user_id' => $request->user()->id, 'key' => $validated['key']],
            );

            $setting->update(['value' => (int) $validated['value']]);

            return response()->json([
                'success' => true,
                'message' => 'Setting updated successfully.',
                'data' => [
                    'setting' => $validated['key'],
                    'value' => $validated['value'],
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->validator->errors(),
                'message' => $e->getMessage(),
            ],422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ],400);
        }
    }

}

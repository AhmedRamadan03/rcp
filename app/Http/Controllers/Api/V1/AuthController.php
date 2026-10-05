<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Actions\AuthRegister;
use App\Events\FailedLoginAttemptEvent;
use App\Events\ResetPasswordEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    /**
     * Login user and create token
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email:filter',
            'password' => 'required|string',
            'remember_me' => 'sometimes|boolean',
        ]);

        $email = strtolower(trim($request->email));

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        // ====== Invalid credentials ======
        if (! $user || ! Hash::check($request->password, $user->password)) {

            if ($user) {
                (new AuthRegister)->failedLogin($user);
                $key = 'failed_login_attempts_' . ($user?->id ?? $request->ip());

                $attempts = Cache::get($key, 0) + 1;
                Cache::put($key, $attempts, now()->addMinutes(10));
                if ($user && $attempts === 3) {
                    event(new FailedLoginAttemptEvent($user));
                }
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Optional: revoke old tokens
        // $user->currentAccessToken()->delete();

        $rememberMe = $request->boolean('remember_me');

    // مدة الـ token validity في الداتابيز نفسها
        $tokenExpiration = $rememberMe ? now()->addDays(30) : now()->addHours(8); // null = مفيش expiration

        $newToken = $user->createToken(
            'auth_token',
            ['*'],
            $tokenExpiration
        );

        $token = $newToken->plainTextToken;

        // الكوكي نفسها
        $cookieMinutes = $rememberMe ? 60 * 24 * 30 : 60 * 8; // 0 = session cookie بتتمسح لما يقفل المتصفح

        $isProduction = app()->environment('production');

        $cookie = cookie(
            'auth_token',
            $token,
            $cookieMinutes,
            '/',
            $isProduction ? config('app.session_domain') : null,
            true, // Secure
            true,
            false,
            $isProduction ? 'Lax' : 'None'
        );

        // ====== Successful login ======
        (new AuthRegister)->successfullyLogin($user, $newToken->accessToken->id);

        if ($user->first_time_login) {
            return response()->json([
                'status'           => false,
                'message'          => 'Please change your password before continuing.',
                'first_time_login' => true,
                'token_type'       => 'Bearer',
            ], 403)->withCookie($cookie);
        }

        return response()->json([
            'status'       => true,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ])->withCookie($cookie);
    }
    /**
     * Logout user (Revoke the token)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        (new AuthRegister)->logout($user, $currentToken->id);
        $currentToken->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Get the authenticated User
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }


    public function changePasswordFirstTime(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Wrong user current password',
            ], 422);
        }

        if ($request->current_password == $request->new_password) {
            return response()->json([
                'status' => false,
                'message' => 'Password is same as previous password',
            ], 422);
        }

        // Optional: revoke old tokens
        (new AuthRegister())->logoutAllSessions($user);
        $user->tokens()->delete();
        $tokenExpiration = now()->addHours(8);
        $cookieMinutes   = 60 * 8;

        $newToken = $user->createToken(
            'auth_token',
            ['*'],
            $tokenExpiration
        );
        $token = $newToken->plainTextToken;
        (new AuthRegister)->successfullyLogin($user, $newToken->accessToken->id);
        $isProduction = app()->environment('production');

        $cookie = cookie(
            'auth_token',
            $token,
            $cookieMinutes,
            '/',
            $isProduction ? config('app.session_domain') : null,
            true, // Secure
            true,
            false,
            $isProduction ? 'Lax' : 'None'
        );
        $user->first_time_login = false;

        $user->password = Hash::make($request->new_password);

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password has been changed successfully',
            'token_type'       => 'Bearer',
        ], 200)->withCookie($cookie);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email:filter',
            'token'    => 'required',
            'password' => 'required|min:6|confirmed',
        ]);
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'first_time_login' => false,
                ])->save();
                (new AuthRegister())->logoutAllSessions($user);
                $user->tokens()->delete();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['status' => true, 'message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email:filter'],
        ]);

        $email = strtolower(trim($request->email));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            event(new ResetPasswordEvent($user));
        }

        return response()->json([
            'status' => true,
            'message' => 'If an account with that email exists, a reset link has been sent.',
        ], 200);
    }
}

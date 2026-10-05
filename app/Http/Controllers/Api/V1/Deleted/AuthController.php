<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Actions\AuthRegister;
use App\Http\Controllers\Api\V1\NotAuthorizeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LoginRequest;
use App\Services\AuthService;

class AuthController extends Controller
{
    //

    public function __construct(public AuthService $service) {}

    public function login(LoginRequest $request)
    {
        try {

            $response = $this->service->login($request->validated());

            return response()->json([
                'error' => false,
                'message' => 'login successfully',
                'token' => $response,
            ]);
        } catch (NotAuthorizeException $exception) {
            return response()->json([
                'error' => false,
                'message' => $exception->getMessage(),
            ]);
        }

    }

    public function logout()
    {
        $user = auth()->user();

        (new AuthRegister)->logout($user);

        auth()->user()->tokens()->delete();

        return response()->json([
            'error' => false,
            'message' => 'logout successfully',
        ]);

    }
}

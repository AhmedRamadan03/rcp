<?php

namespace App\Services;

use App\Exceptions\NotAuthorizeException;
use App\Models\User;

class AuthService
{
    public function login($request)
    {

        if (! auth()->attempt($request)) {
            throw new NotAuthorizeException('Wrong Login credentails');
        }

        $user = User::where('email', $request['email'])->first();

        $authToken = $user->createToken('auth-token')->plainTextToken;

        return $authToken;

    }
}

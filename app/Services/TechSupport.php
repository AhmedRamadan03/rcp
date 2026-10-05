<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

class TechSupport
{
    const TECH_SUPPORT_URL = 'https://techsupport.taheiya.sa/api/';

    public static function createUser($user)
    {
        $request = [
            'name' => $user['name'],
            'email' => $user['email'],
            'password' => $user['password'],
            'company_id' => getTechSupportCompanyId(),
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-API-KEY' => uuid_create(),
        ])
            ->post(self::TECH_SUPPORT_URL.'users', $request)
            ->json();

        return $response;
    }

    public static function updateUser($user)
    {
        $request = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password ?? auth()->user()->password,
            'company_id' => getTechSupportCompanyId(),
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-API-KEY' => uuid_create(),
        ])
            ->put(self::TECH_SUPPORT_URL.'users/'.$user->email, $request)
            ->json();

        return $response;
    }

    public static function loginIfExists($user)
    {
        $request = [
            'email' => $user['email'],
            'company_id' => getTechSupportCompanyId(),
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-API-KEY' => uuid_create(),
        ])->post(self::TECH_SUPPORT_URL.'login-user', $request)->json();

        $user = User::find(auth()->user()->id);

        if ($response['success']) {
            self::updateUser($user);

            return $response;
        } else {

            $request['password'] = $user->password;
            $request['company_id'] = getTechSupportCompanyId();
            $request['email'] = $user->email;
            $request['name'] = $user->name;

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-API-KEY' => uuid_create(),
            ])->post(self::TECH_SUPPORT_URL.'users', $request)->json();

            return static::loginIfExists($request);
        }
    }
}

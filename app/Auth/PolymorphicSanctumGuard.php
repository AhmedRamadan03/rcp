<?php

namespace App\Auth;

use Illuminate\Http\Request;
use Laravel\Sanctum\Guard as SanctumGuard;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Contracts\Auth\Guard;

class PolymorphicSanctumGuard implements Guard
{
    protected $user;

    public function user()
    {
        if ($this->user) {
            return $this->user;
        }

        $token = request()->bearerToken();

        if (!$token) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        if (!$accessToken || !$accessToken->tokenable) {
            return null;
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return null;
        }

        $accessToken->tokenable->withAccessToken($accessToken);

        return $this->user = $accessToken->tokenable;
    }

    public function check() { return !is_null($this->user()); }
    public function guest() { return !$this->check(); }
    public function id() { return $this->user()?->getAuthIdentifier(); }
    public function validate(array $credentials = []) { return false; }
    public function setUser($user) { $this->user = $user; return $this; }
    public function hasUser() { return !is_null($this->user); }
}
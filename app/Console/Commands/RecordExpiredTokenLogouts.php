<?php

namespace App\Console\Commands;

use App\Models\AuthenticationLog;
use App\Models\User;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class RecordExpiredTokenLogouts extends Command
{
    protected $signature = 'tokens:record-expired-logouts';

    protected $description = 'Record logout_at on authentication_log rows whose Sanctum token has expired, run before sanctum:prune-expired';

    public function handle()
    {

        PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(500, function ($tokens) {
                foreach ($tokens as $token) {
                    AuthenticationLog::where('personal_access_token_id', $token->id)
                        ->whereNull('logout_at')
                        ->update(['logout_at' => $token->expires_at]);
                }
            });

        return 0;
    }
}

<?php
namespace App\Actions;

use App\Models\Claim;
use Illuminate\Support\Facades\Cache;

class RevertImportedBacklogClaimsAction
{
    public function __construct(private string $cacheKey) {}

    public function execute(): void
    {
        $ids = Cache::get($this->cacheKey, []);

        if (!empty($ids)) {
            Claim::whereIn('id', $ids)->delete();
            Cache::forget($this->cacheKey);
        }
    }
}
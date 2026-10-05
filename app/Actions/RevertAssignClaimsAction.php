<?php

namespace App\Actions;

use App\Models\Assigning;
use App\Models\Claim;
use Illuminate\Support\Facades\Cache;

class RevertAssignClaimsAction
{
    public function __construct(private string $cacheKey) {}

    public function execute(): void
    {
        $ids = Cache::get($this->cacheKey, []);

        if (!empty($ids)) {
            foreach ($ids as $claimId) {
                $current = Assigning::where('claim_id', $claimId)
                    ->where('is_active', 1)
                    ->first();

                if (!$current) {
                    continue;
                }

                if ($current->from === null) {
                    // مفيش assignment قبله → احذفه
                    $current->delete();
                } else {
                    // رجّع الـ from يبقى to تاني
                    $current->update([
                        'to'      => $current->from,
                        'from'    => null,
                    ]);
                }
            }

            Cache::forget($this->cacheKey);
        }
    }
}

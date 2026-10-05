<?php

namespace App\Actions;

use App\Models\Claim;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class RevertBilledClaimsAction
{
    public function __construct(private readonly string $cacheKey) {}

    public function execute(): void
    {
        $ids = Cache::get($this->cacheKey, []);

        if (!empty($ids)) {
            $this->revertClaims($ids);
        }

        Cache::forget($this->cacheKey);
    }

    private function revertClaims(array $ids): void
    {
        // Fetch original values before any update
        $claims = Claim::whereIn('id', $ids)
            ->select('id', 'is_billed')
            ->get()
            ->keyBy('id');

        // Swap each claim to its opposite value atomically
        foreach ($claims as $id => $claim) {
            $wasBilled = $claim->is_billed;
            Claim::where('id', $id)->update([
                'is_billed'    => !$wasBilled,
                'invoice_date' => !$wasBilled ? Carbon::now() : null,
            ]);
        }
    }
}
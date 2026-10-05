<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TopCollectorTarget extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $start_date = $this->calculateDate(collect($this->targets->pluck('start_date')));

        return [
            'name' => $this->name,
            'data' => $this->targets->pluck('achieved')->toArray(),
            'percentage' => $this->targets->pluck('percentage')->toArray(),
            'remaining' => $this->targets->pluck('remaining')->toArray(),
            'categories' => $start_date,
        ];
    }

    public function calculateDate(Collection $dates)
    {
        $dates = $dates->map(function ($date) {
            return Carbon::parse($date)->format('M');
        });

        return $dates;
    }
}

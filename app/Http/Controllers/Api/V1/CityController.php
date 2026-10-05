<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Models\City;
use App\Http\Resources\Api\V1\CityResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CityController extends Controller
{
    use ApiResponse;
    /**
     * Get cities with search 
     */
    public function index(Request $request)
    {
        // Cache plain arrays — Laravel 13 blocks unserializing Eloquent objects
        // unless listed in config('cache.serializable_classes').
        $cities = City::hydrate(
            Cache::remember('cities:v2', 60 * 60, function () {
                return City::query()->get()->toArray();
            })
        );

        if ($request->filled('search')) {
            $cities = $cities->filter(function ($city) use ($request) {
                return Str::contains($city->name, $request->search);
            })->values();
        }

        return $this->successResponse(
            'Cities retrieved successfully',
            CityResource::collection($cities)
        );
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(City $city)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, City $city)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(City $city)
    {
        //
    }
}

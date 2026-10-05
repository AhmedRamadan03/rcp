<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if(City::count() == 0) {
            $json = file_get_contents(__DIR__.'/../../cities_lite.json');

            $cities = json_decode($json, true);

            City::insert($this->formatCities($this->removeDuplicate($cities)));

        } else {
            echo "pass";
        }

//        foreach ($cities as $city) {
//            City::create([
//                'name' => $city['name_ar'].' '.$city['name_en'],
//            ]);
//        }
    }


    public function removeDuplicate(array $cities): array
    {
        return collect($cities)->unique('name_ar')->toArray();
    }

    public function formatCities(array $cities): array
    {
        return collect($cities)->map(function ($city) {
            return [
                'name' => $city['name_ar'] . ' ' . $city['name_en'],
            ];
        })->toArray();
    }
}

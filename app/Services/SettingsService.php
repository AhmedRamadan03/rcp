<?php

namespace App\Services;

use App\Enums\LetterGeneration;
use App\Models\Setting;
use App\Models\ThirdParty;
use App\Models\User;

class SettingsService
{
    public function updateSettings($values)
    {
        // $seeting_id (auth()->user()->client->settings->first()) ? auth()->user()->client->settings->first()->id : Setting::create(["client_id" => auth()->user()->client->id, "values" => json_encode($values)]);
        return Setting::UpdateOrCreate(['client_id' => auth()->user()->client->id], ['values' => $values]);
    }

    public function settings()
    {
        $settings = Setting::all();
        if ($settings == null) {
            $this->updateSettings(['Language' => ['Arabic', 'English']]);
            $settings = auth()->user()->client->settings->first();
        }

        return $settings;
    }

    public function thirdParties()
    {
        return auth()->user()->client->third_party;
    }

    public function thirdParty($id)
    {
        $third = ThirdParty::findOrFail($id);

        //        if (auth()->user()->client_id == $third->client_id) {
        return $third;
        //        }

        //        return false;
    }

    public function updateThirdPartyInfo($third_party)
    {
        $third = $this->thirdParty($third_party['id']);

        if ($third) {
            $third->vendor_label = $third_party['vendor_label'];
            $third->values = $third_party['values'];

            $third->save();

            return $third;
        }

        return false;
    }

    public function setDefaultThirdParty($thirdPartyId)
    {
        $third = $this->thirdParty($thirdPartyId);

        ThirdParty::where('type', $third->type)->update([
            'is_active' => false,
        ]);

        $third->is_active = true;
        $third->save();

        //        dd($third);

        return $third;
    }

    public function deleteThirdParty($thirdPartyId, $type)
    {
        $tp_list = ThirdParty::where('type', $type)
            ->get();

        if ($tp_list->count() == 1) {
            return false;
        }

        $third = ThirdParty::find($thirdPartyId);
        $third->delete();

        return true;
    }

    public function saveThirdPartyNew($info, $values): bool
    {
        if ($info['is_active']) {
            ThirdParty::where('type', $info['type'])->update([
                'is_active' => false,
            ]);
        }

        if (ThirdParty::create([
            'vendor_name' => $info['vendor_name'],
            'vendor_label' => $info['vendor_label'],
            'type' => $info['type'],
            'is_active' => $info['is_active'],
            'values' => $values,
            'client_id' => auth()->user()->client_id,
        ])) {
            return true;
        }

        return false;
    }

    public static function getActiveThirdParty($type)
    {

        return ThirdParty::getActiveThirdParty($type)->first();

    }

    public static function canGenerateLetters()
    {
        $letters_generation = Setting::where('key', 'claims.letters_generation')->first();
        // updates required in `https://projects.zoho.com/portal/taheiya#zp/task-detail/2126144000002660001`
        return $letters_generation && in_array(
            $letters_generation->value,
            [
                LetterGeneration::AUTOMATIC->value,
                LetterGeneration::MANUAL->value
            ]
        );
    }
}

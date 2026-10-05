<?php

namespace App\Http\Resources\Api\V1\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThirdPartyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $values = $this->values;
        $sensitiveKeys = $this->resolveSensitiveKeys();
        $excludedKeys = $this->resolveExcludedKeys();

        foreach ($values as $key => &$value) {
            if (!empty($value) && in_array($key, $sensitiveKeys)) {
                $value = $this->maskSensitiveValue($value);
            }
        }
        unset($value);

        foreach ($excludedKeys as $key) {
            unset($values[$key]);
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'vendor_name' => $this->vendor_name,
            'vendor_label' => $this->vendor_label,
            'config' => $this->config ?? null,
            'is_active' => (bool) $this->is_active,
            'values' => $values,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }


    /**
     * Resolve keys to exclude by diffing stored values against config inputs.
     * Any key in values that has no matching input definition gets excluded.
     */
    private function resolveExcludedKeys(): array
    {
        $inputs = config("third-party.{$this->type}.{$this->vendor_label}.inputs", []);

        $configKeys = array_column($inputs, 'name');

        return array_keys(
            array_filter(
                (array) $this->values,
                fn($key) => !in_array($key, $configKeys),
                ARRAY_FILTER_USE_KEY
            )
        );
    }


    /**
     * Resolve sensitive input names from the vendor config.
     */
    private function resolveSensitiveKeys(): array
    {
        $type = $this->type;         // e.g. 'sms', 'payment'
        $vendor = $this->vendor_label; // e.g. 'unifonic', 'urway'

        $inputs = config("third-party.{$type}.{$vendor}.inputs", []);

        return array_column(
            array_filter($inputs, fn($input) => !empty($input['sensitive'])),
            'name'
        );
    }

    private function maskSensitiveValue($value, $maskChar = '*')
    {
        $length = strlen($value);
        if ($length <= 3) {
            return str_repeat($maskChar, $length);
        } elseif ($length == 4) {
            return substr($value, 0, 1) . str_repeat($maskChar, 3);
        } else {
            $firstVisible = substr($value, 0, 3);
            $maskLength = $length - 4;
            return $firstVisible . str_repeat($maskChar, $maskLength);
        }
    }

}
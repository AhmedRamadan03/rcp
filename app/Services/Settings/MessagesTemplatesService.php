<?php

namespace App\Services\Settings;

use App\Enums\CustomTemplateStatus;
use App\Models\CustomTemplate;
use App\Models\CustomTemplateProviderConfig;
use App\Models\MessageTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MessagesTemplatesService
{
    public function listAllTemplates(int $perPage): LengthAwarePaginator
    {
        return MessageTemplate::with('customTemplates', 'variables')->paginate($perPage);
    }

    public function createCustomTemplate(array $data)
    {
    return DB::transaction(function () use ($data) {
        $clientId = $data['client_id'];
        $templateId = $data['template_id'];

        // Lock every custom template in this category/client so a concurrent
        // create or activate request can't interleave with this one and leave
        // two rows active at once.
        CustomTemplate::where('template_id', $templateId)
            ->where('client_id', $clientId)
            ->lockForUpdate()
            ->get();

        $isFirstCustomTemplate = $this->isFirstCustomTemplateForTemplate($templateId, $clientId);
        $isActive = $isFirstCustomTemplate
            ? CustomTemplateStatus::ACTIVE->value
            : ($data['is_active'] ?? CustomTemplateStatus::INACTIVE->value);

            $customTemplate = CustomTemplate::create([
            'name' => $data['name'],
            'body' => $data['body'],
            'template_id' => $data['template_id'],
            'client_id' => $data['client_id'],
            'is_active' => $isActive,
            'third_party_id' => $data['third_party_id'],
            'provider_template_code' => $data['provider_template_code'] ?? null,
        ]);

        $this->syncProviderConfigs($customTemplate, $data['provider_configs'] ?? []);

        if ((int) $isActive === CustomTemplateStatus::ACTIVE->value) {
            $this->deactivateOtherCustomTemplates($customTemplate);
        }

        return CustomTemplate::withProviderDetails()->findOrFail($customTemplate->id);
        });
    }


    public function findCustomTemplateWithVariables(int $id)
    {
        return CustomTemplate::withVariables()->withProviderDetails()->findOrFail($id);
    }

    public function findCustomTemplateById(int $id)
    {
        return CustomTemplate::findOrFail($id);
    }

    public function updateCustomTemplate(CustomTemplate $customTemplate, array $data)
    {
    return DB::transaction(function () use ($customTemplate, $data) {
        $customTemplate->update([
            'name' => $data['name'],
            'body' => $data['body'],
            'third_party_id' => $data['third_party_id'],
            'provider_template_code' => array_key_exists('provider_template_code', $data)
                ? $data['provider_template_code']
                : $customTemplate->provider_template_code,
        ]);

        if (array_key_exists('provider_configs', $data)) {
            $this->syncProviderConfigs($customTemplate, $data['provider_configs'] ?? []);
        }

        return CustomTemplate::withProviderDetails()->findOrFail($customTemplate->id);
        });
    }

    public function deleteCustomTemplate(CustomTemplate $customTemplate)
    {
        if($customTemplate->is_active == CustomTemplateStatus::ACTIVE->value){
            throw new \Exception("Cannot delete the active custom template. You must set another custom template as active before deleting this one.");
        }

        $customTemplatesCount = CustomTemplate::where('template_id', $customTemplate->template_id)->count();
        if($customTemplatesCount == 1){
            throw new \Exception("Cannot delete the last custom template. At least one custom template must exist.");
        }

        return $customTemplate->delete();
    }

    public function updateCustomTemplateActiveStatus(CustomTemplate $customTemplate)
    {

        return DB::transaction(function () use ($customTemplate) {
            CustomTemplate::where('template_id', $customTemplate->template_id)
                ->where('client_id', $customTemplate->client_id)
                ->lockForUpdate()
                ->get();

            $this->deactivateOtherCustomTemplates($customTemplate);

            $customTemplate->update(['is_active' => CustomTemplateStatus::ACTIVE->value]);

            return $customTemplate->fresh();
        });

    }


    public function isFirstCustomTemplateForTemplate(int $templateId, int $clientId):bool
    {
        return CustomTemplate::where('template_id', $templateId)
                ->where('client_id', $clientId)
                ->count() === 0;
    }


    /**
     * Deactivate every other custom template in the same category/client.
     * Does not touch $customTemplate's own is_active value.
     */
    private function deactivateOtherCustomTemplates(CustomTemplate $customTemplate): void
    {
        CustomTemplate::where('template_id', $customTemplate->template_id)
            ->where('client_id', $customTemplate->client_id)
            ->where('id', '!=', $customTemplate->id)
            ->update(['is_active' => CustomTemplateStatus::INACTIVE->value]);
    }


    /**
     * Sync provider_configs rows for a custom template.
     * Any existing config not present in $providerConfigs is removed.
     *
     * @param array<int, array{template_variable_id: int, provider_key: string}> $providerConfigs
     */
    private function syncProviderConfigs(CustomTemplate $customTemplate, array $providerConfigs): void
    {
        $customTemplate->providerConfigs()->delete();

        if (empty($providerConfigs)) {
            return;
        }

        $rows = array_map(fn($providerConfigs) => [
            'custom_template_id' => $customTemplate->id,
            'template_variable_id' => $providerConfigs['template_variable_id'],
            'provider_key' => $providerConfigs['provider_key'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $providerConfigs);

        CustomTemplateProviderConfig::insert($rows);
    }

}

<?php

namespace App\Services;

use App\Exceptions\NotAuthorizeException;
use App\Models\Claim;
use App\Models\CustomTemplate;
use App\Models\Message;
use Carbon\Carbon;
use Exception;

class MessageService
{
    public function list($claim_id)
    {
        return Message::where('claim_id', $claim_id)
            ->orderBy('id', 'Desc')
            // ->simplePaginate(20);
            ->paginate(2);
    }


    public static function store(array $data): Message
    {
        return Message::create([
            'claim_id' => $data['claim_id'],
            'body' => $data['body'],
            'send_date' => $data['send_date'] ?? Carbon::now(),
            'status' => $data['status'],
            'status_message' => $data['status_message'] ?? null,
            'message_status' => $data['message_status'] ?? null,
            'request' => $data['request'] ?? null,
            'response' => $data['response'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'is_elm_message' => $data['is_elm_message'] ?? false,
            'national_id' => $data['national_id'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'template_model_id' => $data['template_model_id'] ?? null,
            'payment_link_id' => $data['payment_link_id'] ?? null,
        ]);
    }

    public function create($request)
    {
        try {

            $claim = Claim::where('id', $request['claim_id'])->first();

            $message = (new SmsService)->sendSms($claim->debtor_phone, $request['message'], $claim->id);

            return Message::latest()->first();
        } catch (Exception $e) {
            throw new NotAuthorizeException($e->getMessage());
        }
    }

    public function convertTemplate($template_id, $model_id)
    {
        $custom_template = CustomTemplate::with('MessageTemplate.variables')
            ->where('template_id', $template_id)
            ->where('is_active', '1')
            ->first();

        if (! $custom_template || ! $custom_template->MessageTemplate) {
            throw new Exception('No active template found for this message');
        }

        if ($model_id === null || $model_id === '') {
            throw new Exception('Cannot rebuild message from template: missing template model reference');
        }

        $getModelData = $this->loadModelDataForTemplate($custom_template->MessageTemplate, $model_id);

        if (! $getModelData) {
            throw new Exception('Cannot rebuild message from template: template model not found');
        }

        $custom_message = $custom_template->body;
        foreach ($custom_template->MessageTemplate->variables as $variable) {
            $modelData = $this->resolveVariableValue($variable, $getModelData);
            $custom_message = str_replace($variable->key, $modelData ?? '', $custom_message);
        }

        return $custom_message;
    }

    /**
     * Build the provider_key => value payload for an api_provider custom
     *
     * @return array{provider_template_code: ?string, variables: array<string, mixed>}
     */
    public function buildProviderPayload($template_id, $model_id): array
    {
        $custom_template = CustomTemplate::with(['MessageTemplate', 'providerConfigs.templateVariable'])
            ->where('template_id', $template_id)
            ->where('is_active', '1')
            ->first();

        if (! $custom_template || ! $custom_template->MessageTemplate) {
            return ['provider_template_code' => null, 'variables' => []];
        }

        $getModelData = $this->loadModelDataForTemplate($custom_template->MessageTemplate, $model_id);

        $variables = [];
        foreach ($custom_template->providerConfigs as $config) {
            $variables[$config->provider_key] = $this->resolveVariableValue($config->templateVariable, $getModelData);
        }

        return [
            'provider_template_code' => $custom_template->provider_template_code,
            'variables' => $variables,
        ];
    }

    /**
     * Extracted from the original convertTemplate() loop, unchanged logic —
     * shared by convertTemplate() and buildProviderPayload() so both stay
     */
    private function resolveVariableValue($variable, $getModelData)
    {
        $value = str_replace('.', '->', $variable->value);
        $modelData = $getModelData;

        foreach (explode('->', $value) as $string) {
            if ($modelData === null) {
                $modelData = '';
                break;
            }
            $modelData = $modelData->{$string};
        }

        if ($variable->value == 'payment.link' || $variable->value == 'claim.payment.link') {
            $link = $getModelData?->payment?->link ?? $getModelData?->claim?->payment?->link;
            $modelData = $this->claimInfoUrl($link);
        }

        if ($variable->value == 'link.payment.link') {
            $link = $getModelData?->link?->payment?->link;
            $modelData = $this->claimInfoUrl($link);
        }

        // remove sending the file url 
        if ($variable->value == 'url') {
            //$modelData = route('public.file', ['key' => $getModelData->file?->uuid ?? $getModelData->url]);
            $modelData = '';
        }

        if ($variable->value == 'payment.verification_code' || $variable->value == 'claim.payment.verification_code') {
            $modelData = $getModelData?->payment?->verification_code ?? $getModelData?->claim?->payment?->verification_code;
        }

        if ($modelData && str_contains($variable->value, 'date')) {
            $modelData = Carbon::parse($modelData)->format('Y-m-d');
        }

        return $modelData;
    }

    /**
     * Never emit …/claim-info/ with an empty token (would be charged as a broken SMS).
     */
    private function claimInfoUrl(?string $link): string
    {
        if ($link === null || $link === '') {
            return '';
        }

        return rtrim((string) config('app.frontend_url'), '/') . '/claim-info/' . $link;
    }


    private function loadModelDataForTemplate($messageTemplate, $model_id)
    {
        if (! $messageTemplate || ! $messageTemplate->model) {
            throw new Exception('No active template found for this message');
        }

        if ($model_id === null || $model_id === '') {
            return null;
        }

        $model = $messageTemplate->model;

        $relation = [];
        foreach ($messageTemplate->variables as $variable) {
            if (str_contains($variable->value, '.')) {
                $string = '.' . explode('.', $variable->value)[count(explode('.', $variable->value)) - 1];
                $relation[] = str_replace($string, '', $variable->value);
            }
        }
        $relation = collect($relation)->unique()->all();

        return $model::with($relation)->where('id', $model_id)->first();
    }
}
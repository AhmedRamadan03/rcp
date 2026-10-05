<?php

namespace App\Http\Requests\V1;

use App\Enums\ClientRequest;
use App\Enums\Roles;
use App\Http\Requests\Request as BaseRequest;
use App\Models\Claim;
use Illuminate\Validation\Rule;

class CreateRejectionRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $reason = $this->input('reason');
        $claimId = $this->input('claim_id');

        // Bypass check for exempted reasons
        if ($reason === clientRequest::BANK_TRANSFER->value) {
            return true;
        }

        $claim = Claim::find($claimId);

        // If claim not found, let validation handle it
        if (!$claim) {
            return true;
        }

        return (bool) $claim->client_requests_enabled;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'claim_id' => 'required|numeric|exists:claims,id',
            'reason' => 'required',
            'files' => 'array',
            'files.*' => ['sometimes','file','max:30720', 'mimes:'.implode(',', \App\Enums\UploadFileType::extensions())],
        ];
    }

    public function messages()
    {
        return [
            'claim_id.required' => __('validation.required'),
            'claim_id.numeric' => __('validation.numeric'),
            'claim_id.exists' => 'claim not found',

            'supervisor_id.required' => __('validation.required'),
            'supervisor_id.numeric' => __('validation.numeric'),
            'supervisor_id.exists' => 'supervisor not found',

            'user_id.required' => __('validation.required'),
            'user_id.numeric' => __('validation.numeric'),
            'user_id.exists' => 'user not found',

            'reason.required' => __('validation.required'),

        ];
    }

    public function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'Client requests are currently disabled for this claim.'
        );
    }

}

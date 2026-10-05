<?php

namespace App\Http\Requests\V1\Partner;

use App\Enums\ClaimFileAccess;
use App\Enums\UploadFileType;
use App\Http\Requests\Request;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * Handles the STRUCTURAL validation only for POST /claims (Section 13):
 *  - the body must contain a non-empty `claims` array
 *  - each item must have a `requestItemId`, unique within the request
 *  - documents[{requestItemId}][{index}] shape matches the same rules
 *    as the normal ClaimInfoController::uploadFile() (file/title/access)
 *
 * Per-claim business rules (claim_number, debtor_nid, amount, etc.) are
 * intentionally NOT validated here — those still go through
 * ClaimValidationRules::claimValidationRules() per item inside the
 * controller, because they're conditional on each item's own data
 * (debtor_type, client config, ELM toggle, ...) in a way that doesn't
 * translate cleanly into static wildcard rules for the whole batch.
 *
 * Authorization (credential check) already happened in the
 * AuthenticatePartnerApi middleware before this request class runs, so
 * authorize() simply returns true.
 */
class StorePartnerClaimsRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalizes both accepted input shapes into a single `claims` key
     * before validation runs:
     *  - multipart/form-data: `claims` arrives as a JSON string
     *  - raw JSON body: the array itself IS the top-level body
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('claims') && is_string($this->input('claims'))) {
            $decoded = json_decode($this->input('claims'), true);
            $this->merge(['claims' => is_array($decoded) ? $decoded : []]);
            return;
        }

        if (!$this->has('claims')) {
            $decoded = $this->json()->all();
            if (is_array($decoded) && array_is_list($decoded)) {
                $this->merge(['claims' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'claims' => ['required', 'array', 'min:1'],
            'claims.*.requestItemId' => ['required', 'string', 'distinct'],
            'claims.*.user_email' => ['required', 'email', 'exists:users,email'],

            // Section 13.3 shape: documents[{requestItemId}][{index}][file|title|access]
            // Same per-file rules as ClaimInfoController::uploadFile() —
            // file required, max 20MB, same allowed mimes, access required
            // and must be a valid ClaimFileAccess value. title stays optional
            // (falls back to the original filename, same as the website).
            'documents' => ['sometimes', 'array'],
            'documents.*' => ['array'],
            'documents.*.*.file' => [
                'required',
                'file',
                'max:20480',
                'mimes:' . implode(',', UploadFileType::extensionsWithoutSvg()),
            ],
            'documents.*.*.title' => ['nullable', 'string'],
            'documents.*.*.access_level' => ['required', new Enum(ClaimFileAccess::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'claims.required' => 'Request must contain a non-empty array of claims.',
            'claims.array' => 'Request must contain a non-empty array of claims.',
            'claims.min' => 'Request must contain at least one claim.',
            'claims.*.requestItemId.required' => 'Each claim must include a requestItemId.',
            'claims.*.requestItemId.distinct' => 'requestItemId must be unique within a single request.',
            'documents.*.*.file.required' => 'Each document entry must include a file.',
            'documents.*.*.access_level.required' => 'Each document entry must include an access level.',
        ];
    }

    /**
     * Extra check that can't be expressed as a static rule: every key
     * under `documents` must correspond to a requestItemId that's
     * actually present in `claims` — otherwise a caller could upload
     * a file "orphaned" from any claim by using a made-up correlation id.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $claims = (array) $this->input('claims', []);
            $documents = (array) $this->input('documents', []);
            
            if (empty($documents)) {
                return;
            }
                
            $knownItemIds = collect($claims)->pluck('requestItemId')->filter()->all();

            foreach (array_keys($documents) as $documentItemId) {
                if (!in_array($documentItemId, $knownItemIds)) {
                    $validator->errors()->add(
                        'documents',
                        "documents key '{$documentItemId}' does not match any requestItemId in claims."
                    );
                }
            }
        });
    }
}
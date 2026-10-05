<?php

namespace App\Http\Requests\V1\Partner;

use App\Enums\ClaimFileAccess;
use App\Enums\UploadFileType;
use App\Http\Requests\Request;
use App\Services\Claim\StatusActions\ClaimStatusActionResolver;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * Structural validation only for PATCH /claims (Section 14):
 *  - `claims` must be a non-empty array
 *  - each item's ONLY required field is `reference` (Section 14: "the
 *    only required field") — everything else about a given claim is
 *    validated per-field, per-item, inside the controller, since only
 *    submitted fields should be validated/updated (true partial patch).
 *  - documents[{reference}][{index}] follows the exact same shape and
 *    rules as StorePartnerClaimsRequest, just keyed by `reference`
 *    (already known on update) instead of `requestItemId` (which only
 *    exists pre-creation, on POST).
 *  - statusFile[{reference}] is REQUIRED whenever that item's `status`
 *    action declares requiresFile() === true (e.g. 'close') — checked
 *    via ClaimStatusActionResolver, so this stays correct automatically
 *    as new status actions are added, with no edits needed here.
 */
class UpdatePartnerClaimsRequest extends Request
{
    public function __construct(
        private readonly ClaimStatusActionResolver $statusActionResolver,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

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
            'claims.*.reference' => ['required', 'string'],
            'claims.*.user_email' => ['required', 'email', 'exists:users,email'],


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

            // A single action-specific file per item (e.g. close's
            // close_file), submitted as statusFile[{reference}] —
            // separate from the general `documents` array since it
            // maps to a different model (AdditionalFile, not ClaimFile)
            // depending on the action. Whether it's actually REQUIRED
            // per item is checked in withValidator() below, since that
            // depends on each item's own `status` value, not something
            // a static rule can express.
            'statusFile' => ['sometimes', 'array'],
            'statusFile.*' => [
                'nullable',
                'file',
                'max:20480',
                'mimes:' . implode(',', UploadFileType::extensionsWithoutSvg()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'claims.required' => 'Request must contain a non-empty array of claims.',
            'claims.array' => 'Request must contain a non-empty array of claims.',
            'claims.min' => 'Request must contain at least one claim.',
            'claims.*.reference.required' => 'Each item must include reference.',
        ];
    }

    /**
     * Same cross-check as StorePartnerClaimsRequest, plus the
     * per-item "does this status action require a file" check.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $claims = (array) $this->input('claims', []);
            $knownReferences = collect($claims)->pluck('reference')->filter()->all();

            $documents = (array) $this->input('documents', []);
            foreach (array_keys($documents) as $documentReference) {
                if (!in_array($documentReference, $knownReferences, true)) {
                    $validator->errors()->add(
                        'documents',
                        "documents key '{$documentReference}' does not match any reference in claims."
                    );
                }
            }

            $statusFiles = (array) $this->file('statusFile', []);
            foreach (array_keys($statusFiles) as $statusFileReference) {
                if (!in_array($statusFileReference, $knownReferences, true)) {
                    $validator->errors()->add(
                        'statusFile',
                        "statusFile key '{$statusFileReference}' does not match any reference in claims."
                    );
                }
            }

            // statusFile[{reference}] is required when this item's
            // status action declares requiresFile() === true.
            foreach ($claims as $index => $item) {
                $action = $item['status'] ?? null;
                $reference = $item['reference'] ?? null;

                if ($action === null || $reference === null) {
                    continue;
                }

                if (!$this->statusActionResolver->requiresFile($action)) {
                    continue;
                }

                if (empty($statusFiles[$reference])) {
                    $validator->errors()->add(
                        "claims.{$index}.status",
                        "statusFile['{$reference}'] is required for status '{$action}'."
                    );
                }
            }
        });
    }
}
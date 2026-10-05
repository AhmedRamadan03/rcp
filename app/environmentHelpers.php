<?php

function isVariableExists($variableName): bool
{
    $excludedVariables = collect();
    if (config('app.client_name') == \App\Enums\Clients::GGI->value || config('app.client_name') == \App\Enums\Clients::MIC->value) {
        $excludedVariables = collect([
            '$policy_number',
            '$verification_code',
        ]);
    } elseif (config('app.client_name') == \App\Enums\Clients::GIG->value) {
        $excludedVariables = collect([
            '$link',
            '$url',
        ]);
    }

    return ! $excludedVariables->contains($variableName);
}

function isTemplateExists($template): bool
{
    $excludedTemplates = collect();
    if (config('app.client_name') == \App\Enums\Clients::GGI->value) {
        $excludedTemplates = collect([
            //            \App\Enums\MessageTemplates::APPROVE_DEBTOR->value,
            //            \App\Enums\MessageTemplates::REJECT_DEBTOR->value,

        ]);
    }
    $excludedTemplates->push(\App\Enums\MessageTemplates::BANK_PAYMENT->value);

    return ! $excludedTemplates->contains($template->id);
}

function permissionIsVisible($label): bool
{
    $excludedPermissions = collect([
        'show_claim',
        'show_claims_files',
        'create_settings',
        'delete_settings',
        //'activate_users',
        //'deactivate_users',
        //'create_permissions',
        'create_role',
        //'create_payment_link',
        //'update_payment_link',
        //'activate_payment_link',

        //'create_installment_payment_link',
        //'update_installment_payment_link',
    ]);

    return ! $excludedPermissions->contains($label);
}

function getIVR($type)
{
    return config('app.ivrs.'.$type);
}

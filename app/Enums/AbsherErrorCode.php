<?php

namespace App\Enums;

enum AbsherErrorCode: string
{
    case AuthorizationFailure  = '20009';
    case ClientNotFound        = '20001';
    case TemplateMismatch      = '20003';
    case SchemaValidationError = '30001';

    public function message(): string
    {
        return match($this) {
            self::AuthorizationFailure  => 'Client authorization failure.',
            self::ClientNotFound        => 'Client does not exist.',
            self::TemplateMismatch      => 'Template does not belong to this client.',
            self::SchemaValidationError => 'Invalid NationalOrIqamaId.',
        };
    }

    public function clientMessage(): string
    {
        if ($this->isVisibleToClient()) {
            return $this->message();
        }

        return 'Something went wrong with elm service.';
    }

    public function isVisibleToClient(): bool
    {
        return match($this) {
            self::SchemaValidationError => true,
            default                     => false,
        };
    }

    public function failureType(): SmsFailureType
    {
        return match ($this) {
            self::SchemaValidationError => SmsFailureType::BUSINESS,
            default => SmsFailureType::TECHNICAL,
        };
    }

}
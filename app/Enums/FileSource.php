<?php

namespace App\Enums;

enum FileSource: string
{
    case CLAIM_FILES = 'claim_files';
    case ADDITIONAL_FILES = 'additional_files';
    case UPLOADED_CLAIMS_FILES = 'uploaded_claims_files';
    case VOUCHERS = 'vouchers';
    case REJECTED_CLAIMS = 'rejected_claims';
    case DISCHARGE_FILES = 'discharge_files';
    case CLAIM_STATUS_HISTORY_FILES = 'claim_status_history_files';
    case PROFILE_IMAGES = 'profile_images';
    case OBLIGATION_LETTERS = 'obligation_letters';

    public function publicSource(): bool
    {
        return match($this) {
            self::PROFILE_IMAGES => true,
            default => false,
        };
    }

}

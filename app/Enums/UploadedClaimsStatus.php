<?php

namespace App\Enums;

enum UploadedClaimsStatus: string
{
    case INSERTED = 'inserted';
    case INVALID = 'invalid';
    case UPLOADED = 'uploaded';
    case SCHEDULED = 'scheduled';
    case FAILED = 'failed';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}

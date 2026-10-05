<?php

namespace App\Enums;

enum CloudStorageDisk: string
{
    case GCS_PUBLIC = 'gcs_public';
    case GCS_PRIVATE = 'gcs_private';
}

<?php

namespace App\Enums;

enum StorageDriver: string
{
    case LOCAL = 'local';
    case CLOUD = 'cloud';
}

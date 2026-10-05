<?php

namespace App\Enums;

enum FileVisibility: string
{
    case PUBLISHED = 'published';
    case UNPUBLISHED = 'unpublished';
}

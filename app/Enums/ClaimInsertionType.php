<?php

namespace App\Enums;

enum ClaimInsertionType: string
{
    case FORM = 'form';
    case EXCEL_FILE = 'exports file';
    case API = 'api';
    case BACKLOG = 'backlog';
}

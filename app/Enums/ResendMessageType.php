<?php

namespace App\Enums;

enum ResendMessageType: string
{
    case USE_TEMPLATE = 'use_template';
    case CURRENT_MSG  = 'current_msg';
}
<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case PENDING = 'pending';
    case INVOICED = 'invoiced';
    case TEST = 'test';
   
}

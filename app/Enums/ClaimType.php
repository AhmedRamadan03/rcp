<?php

namespace App\Enums;

enum ClaimType: string
{
    case INDIVIDUAL = 'individual';
    case CORPORATE = 'corporate';
    case LEASING = 'leasing';
    case GOVERNMENT = 'government';
    case MUNICIPALITY = 'municipality';
}

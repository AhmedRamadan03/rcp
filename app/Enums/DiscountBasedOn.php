<?php

namespace App\Enums;

enum DiscountBasedOn: int
{
    case PERCENTAGE = 1;
    case DEDUCTED_AMOUNT = 2;
    case AMOUNT_AFTER_DEDUCTION = 3;

}

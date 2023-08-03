<?php
namespace App\Enums;

enum ConsumablePaymentTypes: int
{
    case NOT_PAID = 0;
    case PAID = 1;
}

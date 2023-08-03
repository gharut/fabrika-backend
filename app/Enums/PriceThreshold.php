<?php

namespace App\Enums;

enum PriceThreshold: string
{
    case Amount = "amount";
    case Percent = "percent";
}

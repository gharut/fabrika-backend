<?php
namespace App\Enums;

enum ConsumableActions: int
{
    case OUT = 0;
    case IN = 1;
    case FIX_IN = 2;
    case FIX_OUT = 3;
    case WASTE = 4;
}

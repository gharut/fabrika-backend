<?php
namespace App\Enums;

enum ServiceApplyTo: string
{
    case ORDER = "ORDER";
    case PRODUCT = "PRODUCT";
    case PRODUCT_COUNT = "PRODUCT_COUNT";
    case PRODUCT_UNIT = "PRODUCT_UNIT";
}

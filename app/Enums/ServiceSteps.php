<?php
namespace App\Enums;

enum ServiceSteps: string
{
    case PREPROCESSING = "PREPROCESSING";
    case PROCESSING = "PROCESSING";
    case FINISHING = "FINISHING";
}

<?php
namespace App\Enums;

enum PaymentType: string {
    case CASH = "CASH";
    case BANK_ACCOUNT = "BANK_ACCOUNT";
    case BANK_CARD = "BANK_CARD";
    case OTHER = "OTHER";
}

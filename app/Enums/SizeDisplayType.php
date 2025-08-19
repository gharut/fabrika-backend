<?php
namespace App\Enums;

enum SizeDisplayType: string
{
    case RUS = 'RUS';
    case TECH = 'TECH';
    case BOTH = 'BOTH';
    
    public function displayValue(): string
    {
        return match($this) {
            self::RUS => 'Рос. размер',
            self::TECH => 'Размер',
            self::BOTH => 'Размер/Рос. размер',
        };
    }
}
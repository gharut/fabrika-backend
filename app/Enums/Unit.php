<?php
namespace App\Enums;

enum Unit: string {
    case Piece = "UNIT_PC";
    case Meter = "UNIT_M";
    case Centimeter = "UNIT_CM";
    case Kilogram = "UNIT_KG";
    case Gram = "UNIT_G";
}

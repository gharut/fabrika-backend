<?php

namespace App\Models;

class LabelPrintOptions
{
    public function __construct(
        public int $sizeId,
        public ?int $labelId,
    ) {}
}

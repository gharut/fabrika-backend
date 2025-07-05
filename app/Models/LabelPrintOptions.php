<?php

namespace App\Models;

class LabelPrintOptions
{
    public function __construct(
        public int $sizeId,
        public ?int $labelId,
        public bool $includeDM = true,
        public bool $includeSHK = false,
        public bool $duplicateDM = false,
    ) {}
}

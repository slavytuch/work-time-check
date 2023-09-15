<?php

namespace Slavytuch\WorkTimeCheck\DTO;

class MyEntryDTO
{
    public function __construct(
        public readonly string $message,
        public readonly \DateInterval $time,
        public readonly string $date
    ) {
    }
}
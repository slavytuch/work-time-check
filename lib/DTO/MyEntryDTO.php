<?php

namespace Slavytuch\WorkTimeCheck\DTO;

class MyEntryDTO
{
    public function __construct(
        public readonly string $task,
        public readonly \DateInterval $time,
        public readonly string $date
    ) {
    }
}
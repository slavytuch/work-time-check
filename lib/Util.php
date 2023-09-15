<?php

namespace Slavytuch\WorkTimeCheck;

class Util
{
    public static function numOfPhrases(int $num, array $phrases): string
    {
        $cases = [2, 0, 1, 1, 1, 2];
        return $phrases[($num % 100 > 4 && $num % 100 < 20) ? 2 : $cases[min($num % 10, 5)]];
    }

    public static function numOfMinutes(int $num)
    {
        return self::numOfPhrases($num, ['минута', 'минуты', 'минут']);
    }
}
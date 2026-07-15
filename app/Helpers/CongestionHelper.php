<?php

namespace App\Helpers;

class CongestionHelper
{
    public static function getText(?float $average): string
    {
        if ($average === null) {
            return '報告なし';
        }
        if ($average >= 2.5) {
            return '混雑';
        }
        if ($average >= 1.5) {
            return 'やや混雑';
        }

        return '空いている';
    }
}

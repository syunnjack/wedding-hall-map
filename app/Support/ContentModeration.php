<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContentModeration
{
    private const NG_WORDS = ['死ね', '殺す', 'バカ', 'カス', 'http://', 'https://', 'www.'];

    public static function containsNgWord(string $text): bool
    {
        foreach (self::NG_WORDS as $word) {
            if ($word !== '' && mb_stripos($text, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function clientIpHash(Request $request): string
    {
        return hash('sha256', $request->ip() ?? 'unknown');
    }

    public static function isTooSoon(string $key, int $seconds): bool
    {
        if (Cache::has($key)) {
            return true;
        }

        Cache::put($key, true, $seconds);

        return false;
    }
}

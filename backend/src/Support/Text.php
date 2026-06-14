<?php

declare(strict_types=1);

namespace Piskari\Support;

final class Text
{
    /**
     * Fold a string to a diacritics-insensitive, lowercase form usable as a
     * search key (e.g. "Křížový vrch" -> "krizovy vrch"). Czech characters are
     * mapped to their ASCII base letters.
     */
    public static function fold(string $value): string
    {
        $map = [
            'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
            'í' => 'i', 'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's',
            'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
            'à' => 'a', 'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ô' => 'o',
            'ł' => 'l', 'ś' => 's', 'ź' => 'z', 'ż' => 'z', 'ć' => 'c',
        ];

        $lower = mb_strtolower($value, 'UTF-8');
        $folded = strtr($lower, $map);

        return trim($folded);
    }
}

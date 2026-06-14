<?php

declare(strict_types=1);

namespace Piskari\Ascent;

final class Protection
{
    /**
     * Allowed protection (jištění) types. Order of this list is the canonical
     * option order; a user's recorded sequence preserves its own order.
     *
     * @var list<string>
     */
    public const TYPES = ['kruh', 'uzel', 'hodiny', 'hrot', 'strom', 'jine'];

    /**
     * Validate and normalise a user-supplied protection sequence: keep only
     * known types, preserving the given order. Returns a list (possibly empty).
     *
     * @param mixed $input
     * @return list<string>
     */
    public static function normalizeSequence(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $result = [];
        foreach ($input as $item) {
            if (is_string($item) && in_array($item, self::TYPES, true)) {
                $result[] = $item;
            }
        }
        return $result;
    }
}

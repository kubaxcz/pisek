<?php

declare(strict_types=1);

namespace Piskari\Tests\Ascent;

use PHPUnit\Framework\TestCase;
use Piskari\Ascent\Protection;

final class ProtectionTest extends TestCase
{
    public function testKeepsKnownTypesInGivenOrder(): void
    {
        $this->assertSame(
            ['uzel', 'kruh', 'strom'],
            Protection::normalizeSequence(['uzel', 'kruh', 'strom']),
        );
    }

    public function testDropsUnknownAndNonStringValues(): void
    {
        $this->assertSame(
            ['kruh', 'hrot'],
            Protection::normalizeSequence(['kruh', 'bogus', 42, null, 'hrot']),
        );
    }

    public function testNonArrayBecomesEmpty(): void
    {
        $this->assertSame([], Protection::normalizeSequence('kruh'));
        $this->assertSame([], Protection::normalizeSequence(null));
    }

    public function testAllowsDuplicatesPreservingOrder(): void
    {
        $this->assertSame(
            ['kruh', 'kruh', 'uzel'],
            Protection::normalizeSequence(['kruh', 'kruh', 'uzel']),
        );
    }
}

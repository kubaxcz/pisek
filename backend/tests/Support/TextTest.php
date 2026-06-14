<?php

declare(strict_types=1);

namespace Piskari\Tests\Support;

use PHPUnit\Framework\TestCase;
use Piskari\Support\Text;

final class TextTest extends TestCase
{
    public function testFoldRemovesCzechDiacriticsAndLowercases(): void
    {
        $this->assertSame('krizovy vrch', Text::fold('Křížový vrch'));
        $this->assertSame('jizni veze', Text::fold('Jižní věže'));
        $this->assertSame('oslik', Text::fold('Oslík'));
    }

    public function testFoldTrimsWhitespace(): void
    {
        $this->assertSame('adrspach', Text::fold('  Adršpach  '));
    }
}

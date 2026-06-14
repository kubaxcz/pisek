<?php

declare(strict_types=1);

namespace Piskari\Tests\Scrape;

use PHPUnit\Framework\TestCase;
use Piskari\Scrape\Parser;

final class ParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    public function testParseSectorsReadsNameUrlAndSeason(): void
    {
        $html = <<<'HTML'
        <table>
          <tr><th>Sektor</th><th>Kdy se může lézt</th></tr>
          <tr><td><a href="/cs/krizovy-vrch/jizni-veze-22/" title="Jižní věže">Jižní věže</a></td><td>lezení bez omezení</td></tr>
          <tr><td><a href="/cs/adrspach/himalaj-1/" title="Himálaj">Himálaj</a></td><td>1.7. &mdash; 30.11.</td></tr>
        </table>
        HTML;

        $sectors = $this->parser->parseSectors($html, 'https://www.piskari.cz/cs/krizovy-vrch/');

        $this->assertCount(2, $sectors);

        $this->assertSame('Jižní věže', $sectors[0]['name']);
        $this->assertSame('https://www.piskari.cz/cs/krizovy-vrch/jizni-veze-22/', $sectors[0]['url']);
        $this->assertNull($sectors[0]['climbing_season']);
        $this->assertSame('lezení bez omezení', $sectors[0]['climbing_restriction']);

        $this->assertSame('Himálaj', $sectors[1]['name']);
        $this->assertSame('1.7. — 30.11.', $sectors[1]['climbing_season']);
    }

    public function testParseRocksCollectsDistinctSkalaLinks(): void
    {
        $html = <<<'HTML'
        <table>
          <tr><td><a href="/cs/skala/amalka-2784/" title="Amálka">Amálka</a></td></tr>
          <tr><td><a href="/cs/skala/brek-2801/" title="Brek">Brek</a></td></tr>
          <tr><td><a href="/cs/skala/amalka-2784/" title="Amálka">Amálka</a></td></tr>
        </table>
        HTML;

        $rocks = $this->parser->parseRocks($html, 'https://www.piskari.cz/cs/krizovy-vrch/jizni-veze-22/');

        $this->assertCount(2, $rocks);
        $this->assertSame('Amálka', $rocks[0]['name']);
        $this->assertSame('https://www.piskari.cz/cs/skala/amalka-2784/', $rocks[0]['url']);
        $this->assertSame('https://www.piskari.cz/cs/skala/brek-2801/', $rocks[1]['url']);
    }

    public function testParseRockExtractsGpsAndRoutes(): void
    {
        $html = <<<'HTML'
        <div><p>GPS: 50°34,687´N, 16°08,675´E.</p></div>
        <table class="vypis vypisCest"><tr>
          <td><img src="/gfx/hodnoceni-cesty/4_small.png" class="hodnoceni-small" alt="Pěkné.">&nbsp;<a href="/cs/cesta/klikarova-direttissima-10254/" title="Klikarova direttissima">Klikarova direttissima</a>&nbsp;VI &nbsp;(<span title="datum prvovýstupu">3.11.1984)</span></td>
          <td><a href="/cs/skala/oslik-2792/">Oslík</a></td>
          <td><a href="/cs/krizovy-vrch/jizni-veze-22/">Jižní věže</a></td>
          <td><span title="počet komentářů k cestě">6 komentářů</span> (<span title="datum posledního komentáře">28.5.2020</span>)<img src="/gfx/fotak.png" class="fotak" alt="u cesty jsou vloženy fotky - 2x"></td>
        </tr><tr>
          <td><img src="/gfx/hodnoceni-cesty/5_small.png" class="hodnoceni-small" alt="Echt gold.">&nbsp;<a href="/cs/cesta/oslovska-1960/">Oslovská</a>&nbsp;V &nbsp;(<span title="datum prvovýstupu">22.8.1986)</span></td>
          <td><a href="/cs/skala/oslik-2792/">Oslík</a></td>
          <td></td>
          <td><span title="počet komentářů k cestě">0 komentářů</span></td>
        </tr></table>
        HTML;

        $rock = $this->parser->parseRock($html, 'https://www.piskari.cz/cs/skala/oslik-2792/');

        $this->assertSame('50°34,687N, 16°08,675E', $rock['gps_raw']);
        $this->assertEqualsWithDelta(50.578117, $rock['gps_lat'], 0.0001);
        $this->assertEqualsWithDelta(16.144583, $rock['gps_lon'], 0.0001);

        $this->assertCount(2, $rock['routes']);

        $first = $rock['routes'][0];
        $this->assertSame('Klikarova direttissima', $first['name']);
        $this->assertSame('https://www.piskari.cz/cs/cesta/klikarova-direttissima-10254/', $first['url']);
        $this->assertSame('VI', $first['difficulty']);
        $this->assertSame('1984-11-03', $first['first_ascent_date']);
        $this->assertSame(1, $first['stars']);
        $this->assertSame(6, $first['comments_count']);
        $this->assertTrue($first['has_photos']);

        $second = $rock['routes'][1];
        $this->assertSame('Oslovská', $second['name']);
        $this->assertSame('V', $second['difficulty']);
        $this->assertSame('1986-08-22', $second['first_ascent_date']);
        $this->assertSame(2, $second['stars']);
        $this->assertSame(0, $second['comments_count']);
        $this->assertFalse($second['has_photos']);
    }

    public function testParseRockWithoutGpsReturnsNulls(): void
    {
        $html = '<table class="vypisCest"></table>';
        $rock = $this->parser->parseRock($html, 'https://www.piskari.cz/cs/skala/oslik-2792/');

        $this->assertNull($rock['gps_raw']);
        $this->assertNull($rock['gps_lat']);
        $this->assertSame([], $rock['routes']);
    }
}

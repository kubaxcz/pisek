<?php

declare(strict_types=1);

namespace Piskari\Scrape;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Parses piskari.cz HTML pages into structured arrays.
 *
 * The selectors below were derived from the live markup:
 *   - area page  : a table with a "Kdy se může lézt" header; each row links a
 *                  sector and gives its climbing season / restriction.
 *   - sector page: rocks are listed as <td><a href="/cs/skala/..."></a></td>.
 *   - rock page  : optional "GPS: .." paragraph and a <table class="vypisCest">
 *                  whose first cell carries the rating icon, route link,
 *                  difficulty and first-ascent date; the last cell carries the
 *                  comment count and an optional photo icon.
 */
final class Parser
{
    /**
     * @return list<array{name:string, url:string, climbing_season:?string, climbing_restriction:?string}>
     */
    public function parseSectors(string $html, string $baseUrl): array
    {
        $xpath = $this->xpath($html);
        $sectors = [];
        $seen = [];

        foreach ($xpath->query('//table') as $table) {
            if (!$table instanceof DOMElement) {
                continue;
            }
            if (!$this->tableHasHeader($xpath, $table, 'Kdy se může')) {
                continue;
            }

            foreach ($xpath->query('.//tr', $table) as $row) {
                $anchor = $this->firstNode($xpath->query('.//td[1]//a[@href]', $row));
                if (!$anchor instanceof DOMElement) {
                    continue;
                }

                $url = $this->absoluteUrl($anchor->getAttribute('href'), $baseUrl);
                if ($url === null || isset($seen[$url])) {
                    continue;
                }

                $cellText = '';
                $secondCell = $this->firstNode($xpath->query('.//td[2]', $row));
                if ($secondCell instanceof DOMNode) {
                    $cellText = $this->cleanText($secondCell->textContent);
                }

                $seen[$url] = true;
                $sectors[] = [
                    'name' => $this->cleanText($anchor->textContent),
                    'url' => $url,
                    'climbing_season' => $this->extractSeason($cellText),
                    'climbing_restriction' => $cellText !== '' ? $cellText : null,
                ];
            }
        }

        return $sectors;
    }

    /**
     * @return list<array{name:string, url:string}>
     */
    public function parseRocks(string $html, string $baseUrl): array
    {
        $xpath = $this->xpath($html);
        $rocks = [];
        $seen = [];

        foreach ($xpath->query("//td/a[contains(@href, '/cs/skala/')]") as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }

            $url = $this->absoluteUrl($anchor->getAttribute('href'), $baseUrl);
            if ($url === null || isset($seen[$url])) {
                continue;
            }

            $name = $this->cleanText($anchor->textContent);
            if ($name === '') {
                continue;
            }

            $seen[$url] = true;
            $rocks[] = ['name' => $name, 'url' => $url];
        }

        return $rocks;
    }

    /**
     * @return array{
     *   gps_raw:?string, gps_lat:?float, gps_lon:?float,
     *   routes: list<array{name:string, url:string, difficulty:?string, first_ascent_date:?string, first_ascent_raw:?string, stars:int, comments_count:int, has_photos:bool}>
     * }
     */
    public function parseRock(string $html, string $baseUrl): array
    {
        $gps = $this->parseGps($html);

        $xpath = $this->xpath($html);
        $routes = [];
        $seen = [];

        $table = $this->firstNode($xpath->query("//table[contains(@class, 'vypisCest')]"));
        if ($table instanceof DOMElement) {
            $order = 0;
            foreach ($xpath->query('.//tr', $table) as $row) {
                $anchor = $this->firstNode($xpath->query(".//a[contains(@href, '/cs/cesta/')]", $row));
                if (!$anchor instanceof DOMElement) {
                    continue;
                }

                $url = $this->absoluteUrl($anchor->getAttribute('href'), $baseUrl);
                if ($url === null || isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;

                $fa = $this->parseFirstAscent($xpath, $row);
                $routes[] = [
                    'name' => $this->cleanText($anchor->textContent),
                    'url' => $url,
                    'difficulty' => $this->parseDifficulty($anchor),
                    'first_ascent_date' => $fa['iso'],
                    'first_ascent_raw' => $fa['raw'],
                    'stars' => $this->parseStars($xpath, $row),
                    'comments_count' => $this->parseComments($xpath, $row),
                    'has_photos' => $this->firstNode($xpath->query(".//img[contains(@class, 'fotak')]", $row)) !== null,
                    'sort_order' => $order++,
                ];
            }
        }

        return [
            'gps_raw' => $gps['raw'],
            'gps_lat' => $gps['lat'],
            'gps_lon' => $gps['lon'],
            'routes' => $routes,
        ];
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private function tableHasHeader(DOMXPath $xpath, DOMElement $table, string $needle): bool
    {
        foreach ($xpath->query('.//th', $table) as $th) {
            if (str_contains($th->textContent, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function firstNode(\DOMNodeList|false $list): ?DOMNode
    {
        if ($list === false || $list->length === 0) {
            return null;
        }
        return $list->item(0);
    }

    private function extractSeason(string $text): ?string
    {
        // e.g. "1.7. — 30.11." or "1.5. – 30.11."
        if (preg_match('/\d{1,2}\.\s*\d{0,2}\.?\s*[—–-]\s*\d{1,2}\.\s*\d{0,2}\.?/u', $text, $m) === 1) {
            return $this->cleanText($m[0]);
        }
        return null;
    }

    /**
     * Difficulty is the text node(s) directly after the route anchor and before
     * the first-ascent span, e.g. "VI" in "<a>..</a>&nbsp;VI &nbsp;(<span>..".
     */
    private function parseDifficulty(DOMElement $anchor): ?string
    {
        $parts = [];
        for ($node = $anchor->nextSibling; $node !== null; $node = $node->nextSibling) {
            if ($node instanceof DOMElement) {
                // Stop at the first-ascent span (or any nested element).
                break;
            }
            $parts[] = $node->textContent;
        }

        $text = $this->cleanText(implode('', $parts));
        $text = trim(str_replace('(', '', $text));

        return $text !== '' ? $text : null;
    }

    /**
     * @return array{iso:?string, raw:?string}
     */
    private function parseFirstAscent(DOMXPath $xpath, DOMNode $row): array
    {
        $span = $this->firstNode($xpath->query(".//span[@title='datum prvovýstupu']", $row));
        if (!$span instanceof DOMNode) {
            return ['iso' => null, 'raw' => null];
        }

        $raw = $this->cleanText(str_replace([')', '('], '', $span->textContent));
        if ($raw === '') {
            return ['iso' => null, 'raw' => null];
        }

        $iso = null;
        if (preg_match('/(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})/', $raw, $m) === 1) {
            $iso = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return ['iso' => $iso, 'raw' => $raw];
    }

    /**
     * Rating icon /gfx/hodnoceni-cesty/N_small.png maps to stars:
     *   N<=3 -> 0 (normal/below), 4 -> 1 (nice), 5 -> 2 (gold).
     */
    private function parseStars(DOMXPath $xpath, DOMNode $row): int
    {
        $img = $this->firstNode($xpath->query(".//img[contains(@class, 'hodnoceni-small')]", $row));
        if (!$img instanceof DOMElement) {
            return 0;
        }

        if (preg_match('#hodnoceni-cesty/(\d+)_#', $img->getAttribute('src'), $m) !== 1) {
            return 0;
        }

        $stars = (int) $m[1] - 3;
        return max(0, min(2, $stars));
    }

    private function parseComments(DOMXPath $xpath, DOMNode $row): int
    {
        $span = $this->firstNode($xpath->query(".//span[@title='počet komentářů k cestě']", $row));
        if (!$span instanceof DOMNode) {
            return 0;
        }

        if (preg_match('/\d+/', $span->textContent, $m) === 1) {
            return (int) $m[0];
        }

        return 0;
    }

    /**
     * @return array{raw:?string, lat:?float, lon:?float}
     */
    private function parseGps(string $html): array
    {
        $pattern = '/GPS:\s*(\d+)°(\d+(?:,\d+)?)[´\'’]?\s*([NS])[,\s]+(\d+)°(\d+(?:,\d+)?)[´\'’]?\s*([EW])/u';
        if (preg_match($pattern, $html, $m) !== 1) {
            return ['raw' => null, 'lat' => null, 'lon' => null];
        }

        $lat = $this->dmToDecimal((int) $m[1], $m[2], $m[3]);
        $lon = $this->dmToDecimal((int) $m[4], $m[5], $m[6]);

        $raw = sprintf('%d°%s%s, %d°%s%s', (int) $m[1], $m[2], $m[3], (int) $m[4], $m[5], $m[6]);

        return ['raw' => $raw, 'lat' => $lat, 'lon' => $lon];
    }

    private function dmToDecimal(int $degrees, string $minutes, string $hemisphere): float
    {
        $min = (float) str_replace(',', '.', $minutes);
        $value = $degrees + $min / 60.0;
        if ($hemisphere === 'S' || $hemisphere === 'W') {
            $value = -$value;
        }
        return round($value, 6);
    }

    private function absoluteUrl(string $href, string $baseUrl): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $this->stripFragment($href);
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: 'www.piskari.cz';
        $origin = $scheme . '://' . $host;

        if (str_starts_with($href, '/')) {
            return $this->stripFragment($origin . $href);
        }

        return $this->stripFragment(rtrim($baseUrl, '/') . '/' . $href);
    }

    private function stripFragment(string $url): string
    {
        $pos = strpos($url, '#');
        return $pos === false ? $url : substr($url, 0, $pos);
    }

    private function cleanText(string $text): string
    {
        // Normalise non-breaking spaces and collapse whitespace.
        $text = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }
}

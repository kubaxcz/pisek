<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Piskari\Catalog\CatalogController;
use Piskari\Http\Request;
use Piskari\Http\Response;
use Piskari\Scrape\ScrapeController;

$request = Request::fromGlobals();
$path = $request->getPath();
$method = $request->getMethod();

$catalog = new CatalogController();
$scrape = new ScrapeController();

// ----- public catalogue --------------------------------------------------

if ($method === 'GET' && $path === '/api/areas') {
    $catalog->listAreas($request)->send();
    exit;
}

if ($method === 'GET' && preg_match('#^/api/sectors/(\d+)/routes$#', $path, $m) === 1) {
    $catalog->sectorRoutes($request, (int) $m[1])->send();
    exit;
}

if ($method === 'GET' && preg_match('#^/api/rocks/(\d+)$#', $path, $m) === 1) {
    $catalog->rock($request, (int) $m[1])->send();
    exit;
}

if ($method === 'GET' && $path === '/api/search') {
    $catalog->search($request)->send();
    exit;
}

// ----- admin: scraping ----------------------------------------------------

if ($method === 'GET' && $path === '/api/admin/scrape/runs') {
    $scrape->listRuns($request)->send();
    exit;
}

if ($method === 'GET' && $path === '/api/admin/scrape/current') {
    $scrape->current($request)->send();
    exit;
}

if ($method === 'POST' && $path === '/api/admin/scrape') {
    $scrape->start($request)->send();
    exit;
}

if ($method === 'POST' && preg_match('#^/api/admin/scrape/runs/(\d+)/step$#', $path, $m) === 1) {
    $scrape->step($request, (int) $m[1])->send();
    exit;
}

if ($method === 'GET' && preg_match('#^/api/admin/scrape/runs/(\d+)$#', $path, $m) === 1) {
    $scrape->getRun($request, (int) $m[1])->send();
    exit;
}

Response::json(['error' => 'Not found'], 404)->send();

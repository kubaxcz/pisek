<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Piskari\Ascent\AscentController;
use Piskari\Auth\AuthController;
use Piskari\Catalog\CatalogController;
use Piskari\Http\Request;
use Piskari\Http\Response;
use Piskari\Scrape\ScrapeController;

$request = Request::fromGlobals();
$path = $request->getPath();
$method = $request->getMethod();

// Any uncaught failure (most likely the DB being unreachable) becomes a clean
// JSON error the frontend can render, rather than an HTML fatal or a hang.
set_exception_handler(static function (\Throwable $e): void {
    $isDb = $e instanceof \PDOException;
    Response::json([
        'error' => $isDb ? 'Databáze je nedostupná.' : 'Interní chyba serveru.',
        'detail' => $e->getMessage(),
    ], $isDb ? 503 : 500)->send();
});

$catalog = new CatalogController();
$scrape = new ScrapeController();
$auth = new AuthController();
$ascent = new AscentController();

// ----- auth & config ------------------------------------------------------

if ($method === 'GET' && $path === '/api/config') {
    $auth->config($request)->send();
    exit;
}

if ($method === 'POST' && $path === '/api/auth/google') {
    $auth->googleLogin($request)->send();
    exit;
}

if ($method === 'GET' && $path === '/api/auth/me') {
    $auth->me($request)->send();
    exit;
}

if ($method === 'POST' && $path === '/api/auth/logout') {
    $auth->logout($request)->send();
    exit;
}

// ----- public catalogue --------------------------------------------------

if ($method === 'GET' && $path === '/api/areas') {
    $catalog->listAreas($request)->send();
    exit;
}

// ----- per-user route entries (ascents) -----------------------------------

if (preg_match('#^/api/routes/(\d+)/ascent$#', $path, $m) === 1) {
    $routeId = (int) $m[1];
    if ($method === 'PUT') {
        $ascent->save($request, $routeId)->send();
        exit;
    }
    if ($method === 'DELETE') {
        $ascent->delete($request, $routeId)->send();
        exit;
    }
}

if ($method === 'GET' && preg_match('#^/api/routes/(\d+)/ascents$#', $path, $m) === 1) {
    $ascent->detail($request, (int) $m[1])->send();
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

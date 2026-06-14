<?php

declare(strict_types=1);

namespace Piskari\Catalog;

use Piskari\Http\Request;
use Piskari\Http\Response;

final class CatalogController
{
    public function __construct(
        private readonly CatalogRepository $repo = new CatalogRepository()
    ) {
    }

    public function listAreas(Request $request): Response
    {
        return Response::json(['areas' => $this->repo->areasWithSectors()]);
    }

    public function sectorRoutes(Request $request, int $sectorId): Response
    {
        $sector = $this->repo->sector($sectorId);
        if ($sector === null) {
            return Response::json(['error' => 'Sector not found'], 404);
        }

        return Response::json([
            'sector' => $sector,
            'routes' => $this->repo->routesInSector($sectorId),
        ]);
    }

    public function rock(Request $request, int $rockId): Response
    {
        $rock = $this->repo->rock($rockId);
        if ($rock === null) {
            return Response::json(['error' => 'Rock not found'], 404);
        }

        return Response::json(['rock' => $rock]);
    }

    public function search(Request $request): Response
    {
        $query = trim($request->getQueryParam('q') ?? '');
        if (mb_strlen($query) < 2) {
            return Response::json(['rocks' => [], 'routes' => [], 'query' => $query]);
        }

        $results = $this->repo->search($query);
        return Response::json([
            'query' => $query,
            'rocks' => $results['rocks'],
            'routes' => $results['routes'],
        ]);
    }
}

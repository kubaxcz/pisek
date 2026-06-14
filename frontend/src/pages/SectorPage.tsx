import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api';
import { useAsync } from '../hooks/useAsync';
import { RouteItem } from '../components/RouteItem';
import type { Route } from '../types';

type SortKey = 'rock' | 'difficulty' | 'stars';

export function SectorPage() {
  const { sectorId } = useParams();
  const id = Number(sectorId);
  const { data, loading, error } = useAsync(() => api.sectorRoutes(id), [id]);

  const [sort, setSort] = useState<SortKey>('rock');
  const [minStars, setMinStars] = useState(0);

  const routes = useMemo(() => {
    if (!data) return [];
    const filtered = data.routes.filter((r) => r.stars >= minStars);
    return sortRoutes(filtered, sort);
  }, [data, sort, minStars]);

  if (loading) return <p className="muted">Načítám…</p>;
  if (error) return <div className="error">{error}</div>;
  if (!data) return null;

  return (
    <>
      <div className="breadcrumb">
        <Link to="/">Oblasti</Link> › {data.sector.area_name}
      </div>
      <h1>{data.sector.name}</h1>
      {data.sector.climbing_season || data.sector.climbing_restriction ? (
        <p className="muted">
          Kdy se může lézt: {data.sector.climbing_season ?? data.sector.climbing_restriction}
        </p>
      ) : null}

      <div className="toolbar">
        <select value={sort} onChange={(e) => setSort(e.target.value as SortKey)} aria-label="Řazení">
          <option value="rock">Řadit dle skály</option>
          <option value="difficulty">Řadit dle obtížnosti</option>
          <option value="stars">Řadit dle hvězdiček</option>
        </select>
        <select
          value={minStars}
          onChange={(e) => setMinStars(Number(e.target.value))}
          aria-label="Filtr hvězdiček"
        >
          <option value={0}>Všechny cesty</option>
          <option value={1}>★ a více</option>
          <option value={2}>★★ jen zlaté</option>
        </select>
        <span className="muted" style={{ alignSelf: 'center' }}>
          {routes.length} cest
        </span>
      </div>

      {routes.length === 0 ? (
        <p className="muted">Žádné cesty.</p>
      ) : (
        <ul className="routes">
          {routes.map((r) => (
            <RouteItem key={r.id} route={r} showRock />
          ))}
        </ul>
      )}
    </>
  );
}

function sortRoutes(routes: Route[], sort: SortKey): Route[] {
  const copy = [...routes];
  if (sort === 'stars') {
    copy.sort((a, b) => b.stars - a.stars || a.name.localeCompare(b.name, 'cs'));
  } else if (sort === 'difficulty') {
    copy.sort((a, b) => (a.difficulty ?? '').localeCompare(b.difficulty ?? '', 'cs'));
  } else {
    copy.sort(
      (a, b) =>
        (a.rock_name ?? '').localeCompare(b.rock_name ?? '', 'cs') ||
        a.name.localeCompare(b.name, 'cs'),
    );
  }
  return copy;
}

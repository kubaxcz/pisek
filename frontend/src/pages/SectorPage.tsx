import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api';
import { useAsync } from '../hooks/useAsync';
import { RouteItem } from '../components/RouteItem';
import type { Route } from '../types';

interface RockGroup {
  rockId: number;
  rockName: string;
  routes: Route[];
}

export function SectorPage() {
  const { sectorId } = useParams();
  const id = Number(sectorId);
  const { data, loading, error } = useAsync(() => api.sectorRoutes(id), [id]);

  const [minStars, setMinStars] = useState(0);

  const groups = useMemo<RockGroup[]>(() => {
    if (!data) return [];
    const byRock = new Map<number, RockGroup>();
    for (const route of data.routes) {
      if (route.stars < minStars) continue;
      const rockId = route.rock_id ?? 0;
      let group = byRock.get(rockId);
      if (!group) {
        group = { rockId, rockName: route.rock_name ?? '—', routes: [] };
        byRock.set(rockId, group);
      }
      group.routes.push(route);
    }
    return [...byRock.values()].sort((a, b) => a.rockName.localeCompare(b.rockName, 'cs'));
  }, [data, minStars]);

  if (loading) return <p className="muted">Načítám…</p>;
  if (error) return <div className="error">{error}</div>;
  if (!data) return null;

  const totalRoutes = groups.reduce((n, g) => n + g.routes.length, 0);

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
          {groups.length} skal · {totalRoutes} cest
        </span>
      </div>

      {groups.length === 0 ? (
        <p className="muted">Žádné cesty.</p>
      ) : (
        groups.map((group) => (
          <section key={group.rockId} className="rock-group">
            <h2 className="rock-group__title">
              <Link to={`/skala/${group.rockId}`}>{group.rockName}</Link>{' '}
              <span className="muted">({group.routes.length})</span>
            </h2>
            <ul className="routes">
              {group.routes.map((r) => (
                <RouteItem key={r.id} route={r} />
              ))}
            </ul>
          </section>
        ))
      )}
    </>
  );
}

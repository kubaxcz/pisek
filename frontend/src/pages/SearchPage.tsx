import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../api';
import { useAsync } from '../hooks/useAsync';
import { RouteItem } from '../components/RouteItem';

export function SearchPage() {
  const [params] = useSearchParams();
  const query = params.get('q')?.trim() ?? '';

  const { data, loading, error } = useAsync(
    () => (query.length >= 2 ? api.search(query) : Promise.resolve({ query, rocks: [], routes: [] })),
    [query],
  );

  if (query.length < 2) {
    return <p className="muted">Zadej alespoň 2 znaky.</p>;
  }
  if (loading) return <p className="muted">Hledám…</p>;
  if (error) return <div className="error">{error}</div>;
  if (!data) return null;

  const empty = data.rocks.length === 0 && data.routes.length === 0;

  return (
    <>
      <h1>Výsledky: „{query}"</h1>
      {empty ? <p className="muted">Nic nenalezeno.</p> : null}

      {data.rocks.length > 0 ? (
        <>
          <h2>Skály ({data.rocks.length})</h2>
          {data.rocks.map((rock) => (
            <Link key={rock.id} to={`/skala/${rock.id}`} className="card" style={{ display: 'block' }}>
              <span className="sector-row__name">{rock.name}</span>
              <div className="muted">
                {rock.area_name} › {rock.sub_area_name}
              </div>
            </Link>
          ))}
        </>
      ) : null}

      {data.routes.length > 0 ? (
        <>
          <h2>Cesty ({data.routes.length})</h2>
          <ul className="routes">
            {data.routes.map((r) => (
              <RouteItem key={r.id} route={r} showRock />
            ))}
          </ul>
        </>
      ) : null}
    </>
  );
}

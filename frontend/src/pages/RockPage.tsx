import { Link, useParams } from 'react-router-dom';
import { api } from '../api';
import { useAsync } from '../hooks/useAsync';
import { RouteItem } from '../components/RouteItem';

export function RockPage() {
  const { rockId } = useParams();
  const id = Number(rockId);
  const { data: rock, loading, error } = useAsync(() => api.rock(id), [id]);

  if (loading) return <p className="muted">Načítám…</p>;
  if (error) return <div className="error">{error}</div>;
  if (!rock) return null;

  return (
    <>
      <div className="breadcrumb">
        <Link to="/">Oblasti</Link> › {rock.area_name} ›{' '}
        <Link to={`/sektor/${rock.sector_id}`}>{rock.sub_area_name}</Link>
      </div>
      <h1>{rock.name}</h1>

      <div className="card">
        <div>
          <span className="muted">Oblast:</span> {rock.area_name}
        </div>
        <div>
          <span className="muted">Podoblast:</span> {rock.sub_area_name}
        </div>
        {rock.gps_raw ? (
          <div>
            <span className="muted">GPS:</span>{' '}
            {rock.gps_lat != null && rock.gps_lon != null ? (
              <a
                href={`https://www.google.com/maps/search/?api=1&query=${rock.gps_lat},${rock.gps_lon}`}
                target="_blank"
                rel="noopener noreferrer"
              >
                {rock.gps_raw} ↗
              </a>
            ) : (
              rock.gps_raw
            )}
          </div>
        ) : null}
        <div style={{ marginTop: '0.4rem' }}>
          <a href={rock.url} target="_blank" rel="noopener noreferrer">
            Otevřít skálu na piskari.cz ↗
          </a>
        </div>
      </div>

      <h2>Cesty ({rock.routes.length})</h2>
      {rock.routes.length === 0 ? (
        <p className="muted">Žádné cesty.</p>
      ) : (
        <ul className="routes">
          {rock.routes.map((r) => (
            <RouteItem key={r.id} route={r} />
          ))}
        </ul>
      )}
    </>
  );
}

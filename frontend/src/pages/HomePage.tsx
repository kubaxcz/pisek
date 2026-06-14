import { Link } from 'react-router-dom';
import { api } from '../api';
import { useAsync } from '../hooks/useAsync';

export function HomePage() {
  const { data: areas, loading, error } = useAsync(() => api.areas(), []);

  if (loading) return <p className="muted">Načítám…</p>;
  if (error) return <div className="error">{error}</div>;
  if (!areas || areas.length === 0) {
    return (
      <>
        <h1>Oblasti</h1>
        <p className="muted">
          Zatím nejsou žádná data. Spusť scraping v sekci <Link to="/admin">Admin</Link>.
        </p>
      </>
    );
  }

  return (
    <>
      <h1>Oblasti</h1>
      {areas.map((area) => (
        <section key={area.id}>
          <h2>{area.name}</h2>
          {area.sectors.length === 0 ? (
            <p className="muted">Žádné obvody.</p>
          ) : (
            area.sectors.map((sector) => (
              <Link key={sector.id} to={`/sektor/${sector.id}`} className="card" style={{ display: 'block' }}>
                <div className="sector-row">
                  <span className="sector-row__name">{sector.name}</span>
                  {sector.climbing_restriction ? (
                    <span
                      className={
                        'badge' +
                        (sector.climbing_restriction.includes('omezením') ? ' badge--warn' : '')
                      }
                    >
                      {sector.climbing_season ?? sector.climbing_restriction}
                    </span>
                  ) : null}
                </div>
                <span className="muted">{sector.rock_count ?? 0} skal</span>
              </Link>
            ))
          )}
        </section>
      ))}
    </>
  );
}

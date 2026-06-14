import { useCallback, useEffect, useRef, useState } from 'react';
import { adminApi } from '../api';
import { useAuth } from '../auth/AuthContext';
import { GoogleLoginButton } from '../auth/GoogleLoginButton';
import type { ScrapeJob, ScrapeRun, ScrapeState } from '../types';

export function AdminPage() {
  const { user, ready } = useAuth();

  if (!ready) {
    return <p className="muted">Načítám…</p>;
  }
  if (!user) {
    return (
      <>
        <h1>Admin</h1>
        <p className="muted">Pro přístup do administrace se přihlas.</p>
        <GoogleLoginButton />
      </>
    );
  }
  if (!user.is_admin) {
    return (
      <>
        <h1>Admin</h1>
        <div className="error">
          Účet {user.email} nemá oprávnění administrátora. (Přidej e-mail do whitelistu
          <code> ADMIN_EMAILS</code>.)
        </div>
      </>
    );
  }
  return <ScrapeDashboard />;
}

function ScrapeDashboard() {
  const [state, setState] = useState<ScrapeState>({ run: null, jobs: [] });
  const [runs, setRuns] = useState<ScrapeRun[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [driving, setDriving] = useState(false);
  const drivingRef = useRef(false);

  const refresh = useCallback(async () => {
    try {
      const [current, runList] = await Promise.all([adminApi.current(), adminApi.listRuns()]);
      setState(current);
      setRuns(runList);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Chyba načítání.');
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  // Drives a run to completion: one synchronous step per request.
  const drive = useCallback(
    async (runId: number) => {
      if (drivingRef.current) return;
      drivingRef.current = true;
      setDriving(true);
      setError(null);
      try {
        // Loop until the backend reports the run is done.
        // eslint-disable-next-line no-constant-condition
        while (true) {
          const res = await adminApi.step(runId);
          setState({ run: res.run, jobs: res.jobs });
          if (res.done || !drivingRef.current) break;
        }
        await adminApi.listRuns().then(setRuns);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Scraping selhal.');
      } finally {
        drivingRef.current = false;
        setDriving(false);
      }
    },
    [],
  );

  async function startScrape() {
    setError(null);
    try {
      const res = await adminApi.start();
      setState(res);
      if (res.run) void drive(res.run.id);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Nelze spustit scraping.');
    }
  }

  const run = state.run;
  const isActive = run && (run.status === 'planned' || run.status === 'running');
  const pending = state.jobs.filter((j) => j.status === 'pending').length;
  const pct = run && run.sectors_total > 0 ? Math.round((run.sectors_done / run.sectors_total) * 100) : 0;

  return (
    <>
      <h1>Scraping</h1>

      {error ? <div className="error">{error}</div> : null}

      <div className="toolbar">
        <button className="primary" onClick={startScrape} disabled={driving || !!isActive}>
          Nový scraping
        </button>
        {isActive && pending > 0 ? (
          <button onClick={() => run && drive(run.id)} disabled={driving}>
            Pokračovat v běžícím ({pending} zbývá)
          </button>
        ) : null}
        <button onClick={refresh} disabled={driving}>
          Obnovit
        </button>
      </div>

      {run ? (
        <div className="card">
          <div className="sector-row">
            <strong>
              Běh #{run.id} — <RunStatus status={run.status} />
            </strong>
            <span className="muted">
              {run.sectors_done}/{run.sectors_total} obvodů
            </span>
          </div>
          <div className="progress">
            <div className="progress__bar" style={{ width: `${pct}%` }} />
          </div>
          <span className="muted">
            {run.rocks_count} skal · {run.routes_count} cest
            {driving ? ' · probíhá…' : ''}
          </span>
          {run.error_message ? <div className="error">{run.error_message}</div> : null}

          {state.jobs.length > 0 ? (
            <ul className="job-list">
              {state.jobs.map((job) => (
                <JobRow key={job.id} job={job} />
              ))}
            </ul>
          ) : null}
        </div>
      ) : (
        <p className="muted">Žádný aktivní běh.</p>
      )}

      <h2>Historie běhů</h2>
      {runs.length === 0 ? (
        <p className="muted">Zatím žádné běhy.</p>
      ) : (
        <ul className="job-list">
          {runs.map((r) => (
            <li key={r.id}>
              <span>
                #{r.id} <RunStatus status={r.status} />
              </span>
              <span className="muted">
                {r.sectors_done}/{r.sectors_total} obvodů · {r.rocks_count} skal · {r.routes_count} cest
              </span>
            </li>
          ))}
        </ul>
      )}
    </>
  );
}

function JobRow({ job }: { job: ScrapeJob }) {
  return (
    <li>
      <span>
        {job.sector_name} <span className="muted">({job.area_name})</span>
      </span>
      <span>
        {job.status === 'done' ? (
          <span className="muted">
            {job.rocks_count ?? 0} skal / {job.routes_count ?? 0} cest{' '}
          </span>
        ) : null}
        <RunStatus status={job.status} />
      </span>
    </li>
  );
}

function RunStatus({ status }: { status: string }) {
  const label: Record<string, string> = {
    planned: 'naplánováno',
    pending: 'čeká',
    running: 'probíhá',
    done: 'hotovo',
    failed: 'chyba',
  };
  return <span className={`status status--${status}`}>{label[status] ?? status}</span>;
}

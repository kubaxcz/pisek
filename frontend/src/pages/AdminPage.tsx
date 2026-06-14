import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import { adminApi, getAdminPassword, setAdminPassword } from '../api';
import type { ScrapeJob, ScrapeRun, ScrapeState } from '../types';

export function AdminPage() {
  const [authed, setAuthed] = useState(getAdminPassword().length > 0);

  if (!authed) {
    return <PasswordGate onAuthed={() => setAuthed(true)} />;
  }
  return <ScrapeDashboard onLogout={() => setAuthed(false)} />;
}

function PasswordGate({ onAuthed }: { onAuthed: () => void }) {
  const [value, setValue] = useState('');

  function submit(e: FormEvent) {
    e.preventDefault();
    setAdminPassword(value.trim());
    onAuthed();
  }

  return (
    <>
      <h1>Admin</h1>
      <form onSubmit={submit}>
        <div className="field">
          <label htmlFor="pwd">Heslo administrátora</label>
          <input
            id="pwd"
            type="password"
            value={value}
            onChange={(e) => setValue(e.target.value)}
            autoComplete="current-password"
          />
        </div>
        <button className="primary" type="submit">
          Přihlásit
        </button>
      </form>
    </>
  );
}

function ScrapeDashboard({ onLogout }: { onLogout: () => void }) {
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

  function logout() {
    setAdminPassword('');
    onLogout();
  }

  const run = state.run;
  const isActive = run && (run.status === 'planned' || run.status === 'running');
  const pending = state.jobs.filter((j) => j.status === 'pending').length;
  const pct = run && run.sectors_total > 0 ? Math.round((run.sectors_done / run.sectors_total) * 100) : 0;

  return (
    <>
      <div className="app-header__bar" style={{ padding: 0, marginBottom: '0.5rem' }}>
        <h1 style={{ margin: 0 }}>Scraping</h1>
        <span className="app-header__spacer" />
        <button onClick={logout}>Odhlásit</button>
      </div>

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

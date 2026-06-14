import { useEffect, useState } from 'react';
import { api } from '../api';
import { useAuth } from '../auth/AuthContext';
import { GoogleLoginButton } from '../auth/GoogleLoginButton';
import type { AscentDetail, ProtectionType, Route } from '../types';
import { Stars } from './Stars';
import { StarInput } from './StarInput';
import { ProtectionPicker, ProtectionSequence } from './ProtectionPicker';

interface Props {
  route: Route;
  onClose: () => void;
  onChanged?: () => void;
}

export function RouteDetailDialog({ route, onClose, onChanged }: Props) {
  const { user, protectionTypes } = useAuth();
  const [detail, setDetail] = useState<AscentDetail | null>(null);
  const [error, setError] = useState<string | null>(null);

  // own-entry form state
  const [routeStars, setRouteStars] = useState<number | null>(null);
  const [belayStars, setBelayStars] = useState<number | null>(null);
  const [protection, setProtection] = useState<ProtectionType[]>([]);
  const [note, setNote] = useState('');
  const [saving, setSaving] = useState(false);

  function loadInto(d: AscentDetail) {
    setDetail(d);
    setRouteStars(d.own?.route_stars ?? null);
    setBelayStars(d.own?.belay_stars ?? null);
    setProtection(d.own?.protection ?? []);
    setNote(d.own?.note ?? '');
  }

  useEffect(() => {
    let active = true;
    api
      .routeAscents(route.id)
      .then((d) => active && loadInto(d))
      .catch((e: unknown) => active && setError(e instanceof Error ? e.message : 'Chyba načítání.'));
    return () => {
      active = false;
    };
  }, [route.id]);

  // Close on Escape.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  async function save() {
    setSaving(true);
    setError(null);
    try {
      await api.saveAscent(route.id, {
        route_stars: routeStars,
        belay_stars: belayStars,
        protection,
        note,
      });
      const fresh = await api.routeAscents(route.id);
      loadInto(fresh);
      onChanged?.();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Uložení selhalo.');
    } finally {
      setSaving(false);
    }
  }

  async function remove() {
    setSaving(true);
    try {
      await api.deleteAscent(route.id);
      const fresh = await api.routeAscents(route.id);
      loadInto(fresh);
      onChanged?.();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Smazání selhalo.');
    } finally {
      setSaving(false);
    }
  }

  const others = (detail?.entries ?? []).filter((e) => !user || e.user_id !== user.id);

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div className="modal" role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
        <div className="modal__head">
          <h2 className="modal__title">
            {route.name} {route.difficulty ? <span className="route__grade">{route.difficulty}</span> : null}
          </h2>
          <button className="modal__close" onClick={onClose} aria-label="Zavřít">
            ×
          </button>
        </div>

        <a className="modal__piskari" href={route.url} target="_blank" rel="noopener noreferrer">
          Otevřít na piskari.cz ↗
        </a>

        {error ? <div className="error">{error}</div> : null}

        {/* Logged-in: own rating form */}
        {user ? (
          <section className="rating-form">
            <h3>Tvé hodnocení</h3>
            <label className="rating-form__row">
              <span>Cesta</span>
              <StarInput value={routeStars} onChange={setRouteStars} label="Hvězdičky cesty" />
            </label>
            <label className="rating-form__row">
              <span>Jištění</span>
              <StarInput value={belayStars} onChange={setBelayStars} label="Hvězdičky jištění" />
            </label>
            <div className="rating-form__block">
              <span>Zakládané jištění (v pořadí)</span>
              <ProtectionPicker options={protectionTypes} value={protection} onChange={setProtection} />
            </div>
            <label className="rating-form__block">
              <span>Poznámka</span>
              <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={3} maxLength={2000} />
            </label>
            <div className="rating-form__actions">
              <button className="primary" onClick={save} disabled={saving}>
                Uložit
              </button>
              {detail?.own ? (
                <button onClick={remove} disabled={saving}>
                  Smazat
                </button>
              ) : null}
            </div>
          </section>
        ) : (
          <section className="rating-form">
            <p className="muted">Pro hodnocení cesty se přihlas.</p>
            <GoogleLoginButton />
          </section>
        )}

        {/* All users' entries */}
        <section>
          <h3>Hodnocení uživatelů ({detail?.entries.length ?? 0})</h3>
          {others.length === 0 && !detail?.own ? (
            <p className="muted">Zatím bez hodnocení.</p>
          ) : (
            <ul className="entries">
              {detail?.own ? <EntryRow name="Ty" entry={detail.own} /> : null}
              {others.map((e) => (
                <EntryRow key={e.user_id} name={e.user_name ?? 'Lezec'} entry={e} />
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  );
}

function EntryRow({
  name,
  entry,
}: {
  name: string;
  entry: { route_stars: number | null; belay_stars: number | null; protection: ProtectionType[]; note: string | null };
}) {
  return (
    <li className="entry">
      <div className="entry__head">
        <strong>{name}</strong>
        <span className="entry__stars">
          {entry.route_stars ? (
            <>
              cesta <Stars value={entry.route_stars} />
            </>
          ) : null}
          {entry.belay_stars ? (
            <>
              {' '}
              · jištění <Stars value={entry.belay_stars} />
            </>
          ) : null}
        </span>
      </div>
      {entry.protection.length > 0 ? <ProtectionSequence value={entry.protection} /> : null}
      {entry.note ? <p className="entry__note">{entry.note}</p> : null}
    </li>
  );
}

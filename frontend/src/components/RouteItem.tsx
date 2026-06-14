import { useState } from 'react';
import type { Route } from '../types';
import { Stars } from './Stars';
import { RouteDetailDialog } from './RouteDetailDialog';

interface RouteItemProps {
  route: Route;
  showRock?: boolean;
}

/**
 * A single route row. Clicking the name opens the detail dialog (ratings,
 * belay info, notes, and — when logged in — the user's own rating form). The ↗
 * link opens the original piskari.cz page in a new tab.
 */
export function RouteItem({ route, showRock }: Readonly<RouteItemProps>) {
  const [open, setOpen] = useState(false);

  const userAvg = route.user_stars_avg ?? null;
  const userCount = route.user_rating_count ?? 0;
  const belayCount = route.belay_count ?? 0;

  return (
    <>
      <li className="route">
        <button className="route__name route__name--btn" onClick={() => setOpen(true)}>
          {route.name}
        </button>
        <a
          className="route__ext"
          href={route.url}
          target="_blank"
          rel="noopener noreferrer"
          title="Otevřít na piskari.cz"
        >
          ↗
        </a>
        <span className="route__grade">{route.difficulty ?? '—'}</span>
        <span className="route__meta">
          {showRock && route.rock_name ? <span>{route.rock_name}</span> : null}
          <Stars value={route.stars} max={2} />
          <span title="počet komentářů">💬 {route.comments_count}</span>
          {route.has_photos ? <span className="icon-photo" title="u cesty jsou fotky" /> : null}
          {userCount > 0 ? (
            <span title="uživatelské hvězdy (počet hodnocení)">
              ⭐ {userAvg} ({userCount})
            </span>
          ) : null}
          {belayCount > 0 ? <span title="záznamů o jištění">🛡 {belayCount}</span> : null}
        </span>
      </li>
      {open ? <RouteDetailDialog route={route} onClose={() => setOpen(false)} /> : null}
    </>
  );
}

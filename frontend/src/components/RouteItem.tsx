import type { Route } from '../types';
import { Stars } from './Stars';

interface RouteItemProps {
  route: Route;
  showRock?: boolean;
}

/**
 * A single route row. Clicking the name opens the original piskari.cz page in
 * a new tab.
 */
export function RouteItem({ route, showRock }: RouteItemProps) {
  return (
    <li className="route">
      <a
        className="route__name"
        href={route.url}
        target="_blank"
        rel="noopener noreferrer"
        title="Otevřít originální stránku na piskari.cz"
      >
        {route.name} ↗
      </a>
      <span className="route__grade">{route.difficulty ?? '—'}</span>
      <span className="route__meta">
        {showRock && route.rock_name ? <span>{route.rock_name}</span> : null}
        <Stars value={route.stars} />
        <span title="počet komentářů">💬 {route.comments_count}</span>
        {route.has_photos ? <span className="icon-photo" title="u cesty jsou fotky" /> : null}
        {route.first_ascent_raw ? <span>1. výstup: {route.first_ascent_raw}</span> : null}
      </span>
    </li>
  );
}

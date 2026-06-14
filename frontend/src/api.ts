import type {
  AppConfig,
  Area,
  AscentDetail,
  AuthUser,
  OwnAscent,
  ProtectionType,
  Rock,
  ScrapeRun,
  ScrapeState,
  SearchResponse,
  SectorRoutesResponse,
  StepResponse,
} from './types';

const TOKEN_KEY = 'piskari.sessionToken';

export function getSessionToken(): string {
  return localStorage.getItem(TOKEN_KEY) ?? '';
}

export function setSessionToken(token: string): void {
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

function authHeaders(json = false): Record<string, string> {
  const headers: Record<string, string> = {};
  const token = getSessionToken();
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (json) headers['Content-Type'] = 'application/json';
  return headers;
}

async function request<T>(url: string, init?: RequestInit): Promise<T> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 12000);
  let res: Response;
  try {
    res = await fetch(url, { ...init, signal: controller.signal });
  } catch (err) {
    if (err instanceof DOMException && err.name === 'AbortError') {
      throw new Error('Server neodpovídá (timeout). Běží backend a databáze?');
    }
    throw new Error('Backend není dostupný. Běží PHP server?');
  } finally {
    clearTimeout(timer);
  }

  if (!res.ok) {
    const body = (await res.json().catch(() => ({}))) as { error?: string };
    throw new Error(body.error ?? `Požadavek selhal (${res.status})`);
  }
  return (await res.json()) as T;
}

const get = <T>(url: string) => request<T>(url, { headers: authHeaders() });

export const api = {
  config: () => get<AppConfig>('/api/config'),

  areas: () => get<{ areas: Area[] }>('/api/areas').then((r) => r.areas),

  sectorRoutes: (sectorId: number) => get<SectorRoutesResponse>(`/api/sectors/${sectorId}/routes`),

  rock: (rockId: number) => get<{ rock: Rock }>(`/api/rocks/${rockId}`).then((r) => r.rock),

  search: (query: string) => get<SearchResponse>(`/api/search?q=${encodeURIComponent(query)}`),

  routeAscents: (routeId: number) => get<AscentDetail>(`/api/routes/${routeId}/ascents`),

  saveAscent: (
    routeId: number,
    payload: { route_stars: number | null; belay_stars: number | null; protection: ProtectionType[]; note: string },
  ) =>
    request<{ own: OwnAscent }>(`/api/routes/${routeId}/ascent`, {
      method: 'PUT',
      headers: authHeaders(true),
      body: JSON.stringify(payload),
    }),

  deleteAscent: (routeId: number) =>
    request<{ ok: boolean }>(`/api/routes/${routeId}/ascent`, {
      method: 'DELETE',
      headers: authHeaders(),
    }),
};

// ----- auth ---------------------------------------------------------------

export const authApi = {
  loginWithGoogle: (idToken: string) =>
    request<{ token: string; user: AuthUser }>('/api/auth/google', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id_token: idToken }),
    }),

  me: () => get<{ user: AuthUser | null }>('/api/auth/me').then((r) => r.user),

  logout: () => request<{ ok: boolean }>('/api/auth/logout', { method: 'POST', headers: authHeaders() }),
};

// ----- admin (scraping) — now gated by the logged-in user's is_admin -------

export const adminApi = {
  listRuns: () => get<{ runs: ScrapeRun[] }>('/api/admin/scrape/runs').then((r) => r.runs),

  current: () => get<ScrapeState>('/api/admin/scrape/current'),

  run: (runId: number) => get<ScrapeState>(`/api/admin/scrape/runs/${runId}`),

  start: () =>
    request<ScrapeState>('/api/admin/scrape', { method: 'POST', headers: authHeaders() }),

  step: (runId: number) =>
    request<StepResponse>(`/api/admin/scrape/runs/${runId}/step`, {
      method: 'POST',
      headers: authHeaders(),
    }),
};

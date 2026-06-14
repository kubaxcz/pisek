import type {
  Area,
  Rock,
  ScrapeRun,
  ScrapeState,
  SearchResponse,
  SectorRoutesResponse,
  StepResponse,
} from './types';

const ADMIN_PASSWORD_KEY = 'piskari.adminPassword';

async function getJson<T>(url: string): Promise<T> {
  const res = await fetch(url);
  if (!res.ok) {
    throw new Error(`Požadavek selhal (${res.status})`);
  }
  return (await res.json()) as T;
}

export const api = {
  areas: () => getJson<{ areas: Area[] }>('/api/areas').then((r) => r.areas),

  sectorRoutes: (sectorId: number) =>
    getJson<SectorRoutesResponse>(`/api/sectors/${sectorId}/routes`),

  rock: (rockId: number) => getJson<{ rock: Rock }>(`/api/rocks/${rockId}`).then((r) => r.rock),

  search: (query: string) =>
    getJson<SearchResponse>(`/api/search?q=${encodeURIComponent(query)}`),
};

// ----- admin --------------------------------------------------------------

export function getAdminPassword(): string {
  return localStorage.getItem(ADMIN_PASSWORD_KEY) ?? '';
}

export function setAdminPassword(password: string): void {
  localStorage.setItem(ADMIN_PASSWORD_KEY, password);
}

async function adminJson<T>(url: string, method: 'GET' | 'POST'): Promise<T> {
  const res = await fetch(url, {
    method,
    headers: { 'X-Admin-Password': getAdminPassword() },
  });
  if (res.status === 401) {
    throw new Error('Neplatné heslo administrátora.');
  }
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error((body as { error?: string }).error ?? `Požadavek selhal (${res.status})`);
  }
  return (await res.json()) as T;
}

export const adminApi = {
  listRuns: () => adminJson<{ runs: ScrapeRun[] }>('/api/admin/scrape/runs', 'GET').then((r) => r.runs),

  current: () => adminJson<ScrapeState>('/api/admin/scrape/current', 'GET'),

  run: (runId: number) => adminJson<ScrapeState>(`/api/admin/scrape/runs/${runId}`, 'GET'),

  start: () => adminJson<ScrapeState>('/api/admin/scrape', 'POST'),

  step: (runId: number) =>
    adminJson<StepResponse>(`/api/admin/scrape/runs/${runId}/step`, 'POST'),
};

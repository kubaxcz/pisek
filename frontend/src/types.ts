export interface Sector {
  id: number;
  area_id?: number;
  name: string;
  url: string;
  climbing_season: string | null;
  climbing_restriction: string | null;
  rock_count?: number;
}

export interface Area {
  id: number;
  name: string;
  url: string;
  sectors: Sector[];
}

export interface Route {
  id: number;
  name: string;
  url: string;
  difficulty: string | null;
  first_ascent_date: string | null;
  first_ascent_raw: string | null;
  stars: number;
  comments_count: number;
  has_photos: boolean;
  rock_id?: number;
  rock_name?: string;
}

export interface Rock {
  id: number;
  name: string;
  url: string;
  area_name: string;
  sub_area_name: string;
  gps_raw: string | null;
  gps_lat: number | null;
  gps_lon: number | null;
  sector_id: number;
  sector_name: string;
  routes: Route[];
}

export interface SectorRoutesResponse {
  sector: Sector & { area_name: string };
  routes: Route[];
}

export interface SearchResponse {
  query: string;
  rocks: Array<Pick<Rock, 'id' | 'name' | 'url' | 'area_name' | 'sub_area_name'> & { sector_id: number }>;
  routes: Route[];
}

export interface ScrapeRun {
  id: number;
  status: 'planned' | 'running' | 'done' | 'failed';
  started_at: string;
  finished_at: string | null;
  sectors_total: number;
  sectors_done: number;
  rocks_count: number;
  routes_count: number;
  error_message: string | null;
}

export interface ScrapeJob {
  id: number;
  run_id: number;
  area_name: string;
  sector_name: string;
  sector_url: string;
  status: 'pending' | 'running' | 'done' | 'failed';
  rocks_count: number | null;
  routes_count: number | null;
  error_message: string | null;
}

export interface ScrapeState {
  run: ScrapeRun | null;
  jobs: ScrapeJob[];
}

export interface StepResponse extends ScrapeState {
  done: boolean;
  job: ScrapeJob | null;
}

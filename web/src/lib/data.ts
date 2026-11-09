// Data access for the storefront.
// - With API_URL set (production/staging on Forge), every page reads live data from the Laravel API.
// - Without it (local dev, the claude.ai preview build), it uses the sample data in src/data/comps.ts.
// The API returns the same shapes as src/data/comps.ts (Comp, winners), wrapped in { data: ... }.
import * as sample from '../data/comps';
import type { Comp } from '../data/comps';

export type Winner = (typeof sample.winners)[number];

// Read at runtime on the server (Forge sets it in the site's environment), not baked in at build time.
const API = (globalThis.process?.env?.API_URL || (import.meta.env.API_URL as string | undefined))?.replace(/\/$/, '');

async function get<T>(path: string, fallback: () => T): Promise<T> {
  if (!API) return fallback();
  const res = await fetch(`${API}${path}`, { headers: { Accept: 'application/json' } });
  if (res.status === 404) return null as T;
  if (!res.ok) throw new Error(`API ${path} returned ${res.status}`);
  const json = await res.json();
  return json.data as T;
}

export const getComps = () => get<Comp[]>('/api/v1/competitions', () => sample.comps);
export const getComp = (slug: string) =>
  get<Comp | null>(`/api/v1/competitions/${encodeURIComponent(slug)}`, () => sample.comps.find((c) => c.slug === slug) ?? null);
export const getWinners = () => get<Winner[]>('/api/v1/winners', () => sample.winners);

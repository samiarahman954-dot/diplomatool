import { distanceKm, type Geo } from "./geo";

// A letter takes a base handling time plus one minute per km between district
// centres: Kushtia → Natore is about an hour, Teknaf → Tetulia most of a day.
// supabase/schema.sql uses the same formula server-side; keep them in sync.
export const BASE_MINUTES = 5;
export const MINUTES_PER_KM = 1;

export function deliveryMinutes(geo: Geo, fromDistrictId: string, toDistrictId: string): number {
  const a = geo.district(fromDistrictId);
  const b = geo.district(toDistrictId);
  if (!a || !b) return BASE_MINUTES;
  return Math.round(BASE_MINUTES + distanceKm(a, b) * MINUTES_PER_KM);
}

export function isDelivered(deliverAt: string, now = Date.now()): boolean {
  return new Date(deliverAt).getTime() <= now;
}

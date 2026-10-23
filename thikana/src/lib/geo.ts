// Bangladesh administrative geography (district → upazila → union).
// The dataset is ~370 KB, so it is loaded lazily the first time it is needed.

export interface District {
  id: string;
  en: string;
  bn: string;
  lat: number;
  lon: number;
}
export interface Upazila {
  id: string;
  d: string;
  en: string;
  bn: string;
}
export interface Union {
  id: string;
  u: string;
  en: string;
  bn: string;
}
export interface Geo {
  districts: District[];
  upazilas: Upazila[];
  unions: Union[];
  district: (id: string) => District | undefined;
  upazila: (id: string) => Upazila | undefined;
  union: (id: string) => Union | undefined;
  upazilasOf: (districtId: string) => Upazila[];
  unionsOf: (upazilaId: string) => Union[];
}

let cached: Promise<Geo> | null = null;

export function loadGeo(): Promise<Geo> {
  cached ??= import("../data/bd-geo.json").then((m) => buildGeo(m.default));
  return cached;
}

export function buildGeo(raw: { districts: District[]; upazilas: Upazila[]; unions: Union[] }): Geo {
  const byId = <T extends { id: string }>(xs: T[]) => new Map(xs.map((x) => [x.id, x]));
  const groupBy = <T>(xs: T[], key: (x: T) => string) => {
    const m = new Map<string, T[]>();
    for (const x of xs) m.set(key(x), [...(m.get(key(x)) ?? []), x]);
    return m;
  };
  const d = byId(raw.districts);
  const u = byId(raw.upazilas);
  const n = byId(raw.unions);
  const upByD = groupBy(raw.upazilas, (x) => x.d);
  const unByU = groupBy(raw.unions, (x) => x.u);
  return {
    ...raw,
    district: (id) => d.get(id),
    upazila: (id) => u.get(id),
    union: (id) => n.get(id),
    upazilasOf: (id) => upByD.get(id) ?? [],
    unionsOf: (id) => unByU.get(id) ?? [],
  };
}

/** Great-circle distance in km. */
export function distanceKm(a: { lat: number; lon: number }, b: { lat: number; lon: number }): number {
  const rad = (x: number) => (x * Math.PI) / 180;
  const dLat = rad(b.lat - a.lat);
  const dLon = rad(b.lon - a.lon);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLon / 2) ** 2;
  return 2 * 6371 * Math.asin(Math.sqrt(h));
}

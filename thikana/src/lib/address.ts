import type { Geo } from "./geo";
import type { Lang } from "./i18n";

/**
 * A mailbox address. Unions come from the dataset when possible; pourashavas and
 * anything missing from the dataset are typed in by hand (`unionId` is then empty).
 */
export interface Address {
  districtId: string;
  upazilaId: string;
  unionId: string;
  unionName: string;
  village: string;
}

/**
 * Lowercases and strips spaces/punctuation so "Bagatu para", "Bagatupara" and
 * "bagatu-para" all land in the same mailbox. Bangla letters are kept as-is.
 */
export function slug(s: string): string {
  return s
    .normalize("NFC")
    .toLowerCase()
    .replace(/[\s\p{P}\p{S}]+/gu, "");
}

/** Key of the union (or typed-in pourashava) that a village belongs to. */
export function unionKey(a: Pick<Address, "districtId" | "upazilaId" | "unionId" | "unionName">): string {
  const union = a.unionId ? `u${a.unionId}` : `x${slug(a.unionName)}`;
  return `bd.${a.districtId}.${a.upazilaId}.${union}`;
}

/** Stable mailbox id, e.g. `bd.25.153.u1234.islampur`. */
export function addressKey(a: Address): string {
  return `${unionKey(a)}.${slug(a.village)}`;
}

export function isComplete(a: Partial<Address>): a is Address {
  return Boolean(a.districtId && a.upazilaId && (a.unionId || a.unionName?.trim()) && slug(a.village ?? ""));
}

export function districtIdOfKey(key: string): string | undefined {
  return /^bd\.(\d+)\./.exec(key)?.[1];
}

export interface AddressLines {
  village: string;
  union: string;
  upazila: string;
  district: string;
}

export function addressLines(a: Address, geo: Geo, lang: Lang): AddressLines {
  const pick = (x: { en: string; bn: string } | undefined) => (x ? x[lang] : "?");
  const union = a.unionId ? geo.union(a.unionId) : undefined;
  return {
    village: a.village.trim(),
    union: union ? pick(union) : a.unionName.trim(),
    upazila: pick(geo.upazila(a.upazilaId)),
    district: pick(geo.district(a.districtId)),
  };
}

export function addressText(a: Address, geo: Geo, lang: Lang): string {
  const l = addressLines(a, geo, lang);
  return [l.village, l.union, l.upazila, l.district].join(", ");
}

/** Addresses travel between pages as a compact URL parameter. */
export function encodeAddress(a: Address): string {
  return [a.districtId, a.upazilaId, a.unionId, a.unionName.trim(), a.village.trim()].map(encodeURIComponent).join("~");
}

export function decodeAddress(s: string | null): Address | null {
  if (!s) return null;
  const parts = s.split("~").map(decodeURIComponent);
  if (parts.length !== 5) return null;
  const [districtId, upazilaId, unionId, unionName, village] = parts;
  const a = { districtId, upazilaId, unionId, unionName, village };
  return isComplete(a) ? a : null;
}

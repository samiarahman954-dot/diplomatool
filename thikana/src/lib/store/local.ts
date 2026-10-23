// Demo backend: letters live in this browser's localStorage only.
import { addressKey } from "../address";
import { deliveryMinutes, isDelivered } from "../delivery";
import { loadGeo } from "../geo";
import { HIDE_AFTER_REPORTS } from "../safety";
import type { KnownVillage, Letter, NewLetter, Store } from "./types";

const KEY = "thikana.letters.v1";

interface Stored extends Omit<Letter, "body"> {
  body: string;
  reports: number;
}

function readAll(): Stored[] {
  try {
    return JSON.parse(localStorage.getItem(KEY) ?? "[]") as Stored[];
  } catch {
    return [];
  }
}

function writeAll(xs: Stored[]) {
  try {
    localStorage.setItem(KEY, JSON.stringify(xs));
  } catch {
    // Storage unavailable (private mode): the demo simply forgets.
  }
}

const visible = (l: Stored) => l.reports < HIDE_AFTER_REPORTS;
const toLetter = (l: Stored): Letter => ({ ...l, body: isDelivered(l.deliverAt) ? l.body : null });

export const localStore: Store = {
  mode: "local",
  ready: Promise.resolve("local"),

  async send(n: NewLetter) {
    const geo = await loadGeo();
    const now = Date.now();
    const minutes = deliveryMinutes(geo, n.from.districtId, n.to.districtId);
    const letter: Stored = {
      ...n,
      id: crypto.randomUUID(),
      toKey: addressKey(n.to),
      createdAt: new Date(now).toISOString(),
      deliverAt: new Date(now + minutes * 60_000).toISOString(),
      reports: 0,
    };
    writeAll([letter, ...readAll()]);
    return { id: letter.id, deliverAt: letter.deliverAt };
  },

  async mailbox(key) {
    return readAll()
      .filter((l) => l.toKey === key && visible(l))
      .map(toLetter);
  },

  async recent(limit) {
    return readAll()
      .filter((l) => visible(l) && !l.sealed && isDelivered(l.deliverAt))
      .sort((a, b) => b.deliverAt.localeCompare(a.deliverAt))
      .slice(0, limit)
      .map(toLetter);
  },

  async villages(unionKey) {
    const seen = new Map<string, KnownVillage>();
    for (const l of readAll()) {
      for (const address of [l.to, l.from]) {
        const key = addressKey(address);
        if (!key.startsWith(unionKey + ".")) continue;
        const v = seen.get(key) ?? { key, address, letters: 0 };
        v.letters += 1;
        seen.set(key, v);
      }
    }
    return [...seen.values()].sort((a, b) => b.letters - a.letters);
  },

  async randomMailbox() {
    const xs = readAll().filter((l) => visible(l) && isDelivered(l.deliverAt));
    return xs.length ? xs[Math.floor(Math.random() * xs.length)].to : null;
  },

  async report(id) {
    writeAll(readAll().map((l) => (l.id === id ? { ...l, reports: l.reports + 1 } : l)));
  },
};

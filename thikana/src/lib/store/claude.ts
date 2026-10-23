// Backend for the claude.ai-hosted build: letters live in the artifact's shared
// `db` capability. When this view can't use it (signed out), we fall back to the
// browser-only demo store.
import { addressKey, type Address } from "../address";
import { deliveryMinutes, isDelivered } from "../delivery";
import { loadGeo } from "../geo";
import { HIDE_AFTER_REPORTS } from "../safety";
import { localStore } from "./local";
import type { KnownVillage, Letter, NewLetter, Store } from "./types";

/* eslint-disable @typescript-eslint/no-explicit-any */
type Db = any;
declare global {
  interface Window {
    claude?: { use(name: string): Promise<any> };
  }
}

interface Doc extends Omit<Letter, "id" | "body"> {
  body: string;
  fromKey: string;
  reporters: string[];
}

const LETTERS = "letters";

let dbPromise: Promise<Db | null> | null = null;
const getDb = () => (dbPromise ??= window.claude?.use("db") ?? Promise.resolve(null));

let viewerPromise: Promise<string> | null = null;
function viewerId(): Promise<string> {
  viewerPromise ??= (window.claude?.use("user") ?? Promise.resolve(null))
    .then((u: any) => u?.id())
    .then((id: string | null) => id ?? crypto.randomUUID())
    .catch(() => crypto.randomUUID());
  return viewerPromise;
}

const visible = (d: Doc) => (d.reporters?.length ?? 0) < HIDE_AFTER_REPORTS;

function toLetter(id: string, d: Doc): Letter {
  return {
    id,
    toKey: d.toKey,
    to: d.to,
    from: d.from,
    penName: d.penName,
    body: isDelivered(d.deliverAt) ? d.body : null,
    stamp: d.stamp,
    sealed: d.sealed,
    sealHint: d.sealHint,
    createdAt: d.createdAt,
    deliverAt: d.deliverAt,
    replyTo: d.replyTo,
  };
}

async function query(build: (c: Db) => Db): Promise<Array<{ id: string; d: Doc }>> {
  const db = await getDb();
  const snap = await build(db.collection(LETTERS)).get();
  return snap.docs.map((s: any) => ({ id: s.id, d: s.data() as Doc })).filter((x: { d: Doc }) => visible(x.d));
}

/** Throws a SafetyIssue-style code the Write page knows how to explain. */
function storeError(e: unknown): Error {
  const code = (e as { code?: string })?.code;
  if (code === "invalid_argument") return new Error("readonly");
  if (code === "quota_exceeded") return new Error("full");
  return new Error("generic");
}

export const claudeStore: Store = {
  mode: "claude",
  ready: getDb().then((db) => (db ? "claude" : "local")),

  async send(n: NewLetter) {
    const db = await getDb();
    if (!db) return localStore.send(n);
    const geo = await loadGeo();
    const now = Date.now();
    const doc: Doc = {
      ...n,
      toKey: addressKey(n.to),
      fromKey: addressKey(n.from),
      createdAt: new Date(now).toISOString(),
      deliverAt: new Date(now + deliveryMinutes(geo, n.from.districtId, n.to.districtId) * 60_000).toISOString(),
      reporters: [],
    };
    try {
      const ref = await db.collection(LETTERS).add(doc);
      return { id: ref.id, deliverAt: doc.deliverAt };
    } catch (e) {
      throw storeError(e);
    }
  },

  async mailbox(key) {
    if (!(await getDb())) return localStore.mailbox(key);
    const rows = await query((c) => c.where("toKey", "==", key).orderBy("createdAt", "desc").limit(200));
    return rows.map((r) => toLetter(r.id, r.d));
  },

  async recent(limit) {
    if (!(await getDb())) return localStore.recent(limit);
    const now = new Date().toISOString();
    const rows = await query((c) =>
      c.where("sealed", "==", false).where("deliverAt", "<=", now).orderBy("deliverAt", "desc").limit(limit + 10),
    );
    return rows.slice(0, limit).map((r) => toLetter(r.id, r.d));
  },

  async villages(unionKey) {
    if (!(await getDb())) return localStore.villages(unionKey);
    // "/" sorts right after ".", so this range is every key under the union.
    const [lo, hi] = [unionKey + ".", unionKey + "/"];
    const [to, from] = await Promise.all([
      query((c) => c.where("toKey", ">=", lo).where("toKey", "<", hi).limit(500)),
      query((c) => c.where("fromKey", ">=", lo).where("fromKey", "<", hi).limit(500)),
    ]);
    const seen = new Map<string, KnownVillage>();
    const add = (address: Address) => {
      const key = addressKey(address);
      const v = seen.get(key) ?? { key, address, letters: 0 };
      v.letters += 1;
      seen.set(key, v);
    };
    to.forEach((r) => add(r.d.to));
    from.forEach((r) => add(r.d.from));
    return [...seen.values()].sort((a, b) => b.letters - a.letters);
  },

  async randomMailbox() {
    if (!(await getDb())) return localStore.randomMailbox();
    const now = new Date().toISOString();
    const rows = await query((c) => c.where("deliverAt", "<=", now).orderBy("deliverAt", "desc").limit(200));
    return rows.length ? rows[Math.floor(Math.random() * rows.length)].d.to : null;
  },

  async report(id) {
    const db = await getDb();
    if (!db) return localStore.report(id);
    const ref = db.collection(LETTERS).doc(id);
    const snap = await ref.get();
    if (!snap.exists) return;
    const me = await viewerId();
    const reporters: string[] = snap.data().reporters ?? [];
    if (!reporters.includes(me)) await ref.update({ reporters: [...reporters, me] }).catch(() => {});
  },
};

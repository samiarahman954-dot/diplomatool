// Shared backend. All access goes through the RPC functions in supabase/schema.sql;
// the letters table itself is closed to the anon key by row-level security.
import { createClient } from "@supabase/supabase-js";
import { addressKey, type Address } from "../address";
import type { KnownVillage, Letter, NewLetter, Store } from "./types";

interface Row {
  id: string;
  to_key: string;
  to_addr: Address;
  from_addr: Address;
  pen_name: string;
  body: string | null;
  stamp: string;
  sealed: boolean;
  seal_hint: string;
  created_at: string;
  deliver_at: string;
  reply_to: string | null;
}

const fromRow = (r: Row): Letter => ({
  id: r.id,
  toKey: r.to_key,
  to: r.to_addr,
  from: r.from_addr,
  penName: r.pen_name,
  body: r.body,
  stamp: r.stamp,
  sealed: r.sealed,
  sealHint: r.seal_hint,
  createdAt: r.created_at,
  deliverAt: r.deliver_at,
  replyTo: r.reply_to,
});

function reporterId(): string {
  try {
    let id = localStorage.getItem("thikana.reporter");
    if (!id) {
      id = crypto.randomUUID();
      localStorage.setItem("thikana.reporter", id);
    }
    return id;
  } catch {
    return crypto.randomUUID();
  }
}

export function supabaseStore(url: string, anonKey: string): Store {
  const db = createClient(url, anonKey, { auth: { persistSession: false } });

  async function rpc<T>(fn: string, args: Record<string, unknown>): Promise<T> {
    const { data, error } = await db.rpc(fn, args);
    if (error) throw new Error(error.message);
    return data as T;
  }

  return {
    mode: "supabase",

    async send(n: NewLetter) {
      const rows = await rpc<{ id: string; deliver_at: string }[]>("send_letter", {
        p_to_key: addressKey(n.to),
        p_to_addr: n.to,
        p_from_key: addressKey(n.from),
        p_from_addr: n.from,
        p_pen_name: n.penName,
        p_body: n.body,
        p_stamp: n.stamp,
        p_sealed: n.sealed,
        p_seal_hint: n.sealHint,
        p_reply_to: n.replyTo,
      });
      return { id: rows[0].id, deliverAt: rows[0].deliver_at };
    },

    async mailbox(key) {
      return (await rpc<Row[]>("get_mailbox", { p_key: key })).map(fromRow);
    },

    async recent(limit) {
      return (await rpc<Row[]>("recent_letters", { p_limit: limit })).map(fromRow);
    },

    async villages(unionKey) {
      const rows = await rpc<{ key: string; addr: Address; letters: number }[]>("known_villages", {
        p_union_key: unionKey,
      });
      return rows.map((r): KnownVillage => ({ key: r.key, address: r.addr, letters: Number(r.letters) }));
    },

    async randomMailbox() {
      const rows = await rpc<{ addr: Address }[]>("random_mailbox", {});
      return rows[0]?.addr ?? null;
    },

    async report(id) {
      await rpc("report_letter", { p_id: id, p_reporter: reporterId() });
    },
  };
}

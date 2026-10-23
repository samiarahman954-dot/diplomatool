import type { Address } from "../address";

export interface Letter {
  id: string;
  toKey: string;
  to: Address;
  from: Address;
  penName: string;
  /** Null while the letter is still travelling. Ciphertext when sealed. */
  body: string | null;
  stamp: string;
  sealed: boolean;
  sealHint: string;
  createdAt: string;
  deliverAt: string;
  replyTo: string | null;
}

export interface NewLetter {
  to: Address;
  from: Address;
  penName: string;
  body: string;
  stamp: string;
  sealed: boolean;
  sealHint: string;
  replyTo: string | null;
}

export interface KnownVillage {
  key: string;
  address: Address;
  letters: number;
}

export type StoreMode = "local" | "supabase" | "claude";

export interface Store {
  mode: StoreMode;
  /** The backend this view actually uses (the hosted build falls back to local when signed out). */
  ready: Promise<StoreMode>;
  send(letter: NewLetter): Promise<{ id: string; deliverAt: string }>;
  mailbox(key: string): Promise<Letter[]>;
  recent(limit: number): Promise<Letter[]>;
  /** Villages that already have a mailbox under a union key (for suggestions). */
  villages(unionKey: string): Promise<KnownVillage[]>;
  randomMailbox(): Promise<Address | null>;
  report(id: string): Promise<void>;
}

import { claudeStore } from "./claude";
import { localStore } from "./local";
import { supabaseStore } from "./supabase";
import type { Store } from "./types";

const url = import.meta.env.VITE_SUPABASE_URL as string | undefined;
const key = import.meta.env.VITE_SUPABASE_ANON_KEY as string | undefined;

/** `npm run build:claude` targets claude.ai artifact hosting. */
export const HOSTED_ON_CLAUDE = import.meta.env.VITE_TARGET === "claude";

export const store: Store =
  url && key ? supabaseStore(url, key) : HOSTED_ON_CLAUDE ? claudeStore : localStore;

export type { Letter, NewLetter, KnownVillage, Store, StoreMode } from "./types";

import { localStore } from "./local";
import { supabaseStore } from "./supabase";
import type { Store } from "./types";

const url = import.meta.env.VITE_SUPABASE_URL as string | undefined;
const key = import.meta.env.VITE_SUPABASE_ANON_KEY as string | undefined;

export const store: Store = url && key ? supabaseStore(url, key) : localStore;

export type { Letter, NewLetter, KnownVillage, Store } from "./types";

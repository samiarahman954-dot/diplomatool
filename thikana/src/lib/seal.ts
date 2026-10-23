// Sealed letters are encrypted in the browser with a secret word the sender
// shares out-of-band. The server only ever stores ciphertext.

const ITERATIONS = 150_000;
const enc = new TextEncoder();
const dec = new TextDecoder();

function b64(bytes: Uint8Array): string {
  let s = "";
  for (const b of bytes) s += String.fromCharCode(b);
  return btoa(s);
}
function unb64(s: string): Uint8Array {
  return Uint8Array.from(atob(s), (c) => c.charCodeAt(0));
}

async function keyFor(secret: string, salt: Uint8Array): Promise<CryptoKey> {
  const base = await crypto.subtle.importKey("raw", enc.encode(secret.trim().normalize("NFC")), "PBKDF2", false, [
    "deriveKey",
  ]);
  return crypto.subtle.deriveKey(
    { name: "PBKDF2", salt, iterations: ITERATIONS, hash: "SHA-256" },
    base,
    { name: "AES-GCM", length: 256 },
    false,
    ["encrypt", "decrypt"],
  );
}

/** Returns `seal1.<salt>.<iv>.<ciphertext>` (base64 parts). */
export async function seal(text: string, secret: string): Promise<string> {
  const salt = crypto.getRandomValues(new Uint8Array(16));
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const key = await keyFor(secret, salt);
  const ct = new Uint8Array(await crypto.subtle.encrypt({ name: "AES-GCM", iv }, key, enc.encode(text)));
  return ["seal1", b64(salt), b64(iv), b64(ct)].join(".");
}

/** Returns the plaintext, or null when the secret is wrong. */
export async function unseal(sealed: string, secret: string): Promise<string | null> {
  const [tag, salt, iv, ct] = sealed.split(".");
  if (tag !== "seal1" || !salt || !iv || !ct) return null;
  try {
    const key = await keyFor(secret, unb64(salt));
    return dec.decode(await crypto.subtle.decrypt({ name: "AES-GCM", iv: unb64(iv) }, key, unb64(ct)));
  } catch {
    return null;
  }
}

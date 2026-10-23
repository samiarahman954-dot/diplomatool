import { useCallback, useEffect, useState } from "react";
import { Link, useParams, useSearchParams } from "react-router-dom";
import { Envelope } from "../components/Envelope";
import { LetterView } from "../components/LetterView";
import { addressKey, addressLines, decodeAddress, encodeAddress, type Address } from "../lib/address";
import { useGeo, useNow } from "../lib/hooks";
import { useT } from "../lib/i18n";
import { store, type Letter } from "../lib/store";

export function Mailbox() {
  const { t, lang } = useT();
  const { key = "" } = useParams();
  const [params] = useSearchParams();
  const geo = useGeo();
  const now = useNow(15_000);
  const [letters, setLetters] = useState<Letter[] | null>(null);
  const [open, setOpen] = useState<Letter | null>(null);

  const load = useCallback(() => {
    store
      .mailbox(key)
      .then(setLetters)
      .catch(() => setLetters([]));
  }, [key]);

  useEffect(load, [load]);

  // Reload when the next letter on its way arrives, so its body becomes readable.
  useEffect(() => {
    const next = (letters ?? [])
      .filter((l) => l.body === null)
      .map((l) => new Date(l.deliverAt).getTime())
      .sort((a, b) => a - b)[0];
    if (!next) return;
    const id = setTimeout(load, Math.max(1000, next - Date.now() + 1000));
    return () => clearTimeout(id);
  }, [letters, load]);

  const fromUrl = decodeAddress(params.get("a"));
  const address: Address | undefined =
    fromUrl && addressKey(fromUrl) === key ? fromUrl : letters?.[0]?.to;
  const lines = address && geo ? addressLines(address, geo, lang) : null;

  return (
    <>
      <section className="card">
        <div className="small muted">📮 {t("mailboxOf")}</div>
        {lines ? (
          <div className="address" style={{ margin: "8px 0 16px" }}>
            <strong>{lines.village}</strong>
            <br />
            {lines.union}, {lines.upazila}
            <br />
            {lines.district}
          </div>
        ) : (
          <p className="address">{key}</p>
        )}
        {address && (
          <Link className="btn" to={`/write?to=${encodeAddress(address)}`}>
            ✍️ {t("writeHere")}
          </Link>
        )}
      </section>

      {!geo || letters === null ? (
        <p className="muted">…</p>
      ) : letters.length === 0 ? (
        <p className="muted">{t("empty")}</p>
      ) : (
        <div className="envelopes">
          {letters.map((l) => (
            <Envelope key={l.id} geo={geo} letter={l} now={now} onOpen={() => setOpen(l)} />
          ))}
        </div>
      )}

      {open && geo && <LetterView geo={geo} letter={open} onClose={() => setOpen(null)} />}
    </>
  );
}

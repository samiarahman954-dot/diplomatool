import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { AddressPicker, type Draft } from "../components/AddressPicker";
import { Envelope } from "../components/Envelope";
import { LetterView } from "../components/LetterView";
import { addressKey, encodeAddress, isComplete, type Address } from "../lib/address";
import { useGeo, useNow } from "../lib/hooks";
import { useT } from "../lib/i18n";
import { store, type Letter } from "../lib/store";

export function mailboxPath(a: Address) {
  return `/m/${encodeURIComponent(addressKey(a))}?a=${encodeAddress(a)}`;
}

export function Home() {
  const { t } = useT();
  const geo = useGeo();
  const now = useNow();
  const navigate = useNavigate();
  const [draft, setDraft] = useState<Draft>({});
  const [recent, setRecent] = useState<Letter[]>([]);
  const [open, setOpen] = useState<Letter | null>(null);
  const [noRandom, setNoRandom] = useState(false);

  useEffect(() => {
    store.recent(20).then(setRecent).catch(() => setRecent([]));
  }, []);

  async function random() {
    const a = await store.randomMailbox().catch(() => null);
    if (a) navigate(mailboxPath(a));
    else setNoRandom(true);
  }

  return (
    <>
      <section className="card">
        <h1>✉️ {t("tagline")}</h1>
        <p className="muted">{t("intro")}</p>
        <div className="row">
          <Link className="btn" to="/write">
            {t("writeLetter")}
          </Link>
          <button className="btn ghost" onClick={random}>
            🎲 {t("randomVillage")}
          </button>
        </div>
        {noRandom && <p className="small muted">{t("noRandom")}</p>}
      </section>

      <section className="card">
        <h2>📮 {t("findMailbox")}</h2>
        {geo ? <AddressPicker geo={geo} value={draft} onChange={setDraft} /> : <p className="muted">…</p>}
        <div className="row" style={{ marginTop: 14 }}>
          <button
            className="btn"
            disabled={!isComplete(draft)}
            onClick={() => isComplete(draft) && navigate(mailboxPath(draft))}
          >
            {t("openMailbox")}
          </button>
        </div>
      </section>

      <section>
        <h2>{t("recent")}</h2>
        {!geo ? null : recent.length === 0 ? (
          <p className="muted">{t("noRecent")}</p>
        ) : (
          <div className="envelopes">
            {recent.map((l) => (
              <Envelope key={l.id} geo={geo} letter={l} now={now} showTo onOpen={() => setOpen(l)} />
            ))}
          </div>
        )}
      </section>

      {open && geo && <LetterView geo={geo} letter={open} onClose={() => setOpen(null)} />}
    </>
  );
}

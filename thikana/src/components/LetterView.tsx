import { useEffect, useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import { addressText, encodeAddress } from "../lib/address";
import type { Geo } from "../lib/geo";
import { dateText, useT } from "../lib/i18n";
import { unseal } from "../lib/seal";
import { store, type Letter } from "../lib/store";
import { Stamp } from "./Stamp";

interface Props {
  geo: Geo;
  letter: Letter;
  onClose: () => void;
}

export function LetterView({ geo, letter, onClose }: Props) {
  const { t, lang } = useT();
  const [text, setText] = useState<string | null>(letter.sealed ? null : letter.body);
  const [secret, setSecret] = useState("");
  const [wrong, setWrong] = useState(false);
  const [reported, setReported] = useState(false);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [onClose]);

  async function tryUnseal(e: FormEvent) {
    e.preventDefault();
    const plain = await unseal(letter.body ?? "", secret);
    setWrong(plain === null);
    if (plain !== null) setText(plain);
  }

  async function report() {
    await store.report(letter.id).catch(() => {});
    setReported(true);
  }

  return (
    <div className="overlay" onClick={onClose}>
      <div className="modal card" role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
        <div className="row" style={{ justifyContent: "space-between", alignItems: "flex-start", marginBottom: 12 }}>
          <div>
            <div className="small muted">{dateText(lang, letter.deliverAt)}</div>
            <div className="small muted">
              {t("to")}: {addressText(letter.to, geo, lang)}
            </div>
            {letter.replyTo && <div className="small muted">↩ {t("replyTo")}</div>}
          </div>
          <Stamp id={letter.stamp} />
        </div>

        {text !== null ? (
          <div className="paper">
            {text}
            {"\n\n— "}
            {letter.penName || t("anonymous")}
          </div>
        ) : (
          <form className="paper" onSubmit={tryUnseal} style={{ fontFamily: "var(--font)" }}>
            <div>🔒 {t("sealed")}</div>
            {letter.sealHint && (
              <div className="small muted">
                {t("hint")}: {letter.sealHint}
              </div>
            )}
            <div className="row" style={{ marginTop: 8 }}>
              <input
                type="password"
                aria-label={t("secret")}
                placeholder={t("secret")}
                value={secret}
                onChange={(e) => setSecret(e.target.value)}
                style={{ flex: 1, minWidth: 160 }}
              />
              <button className="btn" type="submit">
                {t("unlock")}
              </button>
            </div>
            {wrong && <p className="error">{t("wrongSecret")}</p>}
          </form>
        )}

        <p className="small muted" style={{ marginTop: 12 }}>
          {t("from_")}: {addressText(letter.from, geo, lang)}
        </p>

        <div className="row" style={{ justifyContent: "space-between" }}>
          <div className="row">
            <Link className="btn" to={`/write?to=${encodeAddress(letter.from)}&reply=${letter.id}`}>
              ↩ {t("reply")}
            </Link>
            <button className="btn ghost" onClick={onClose}>
              {t("close")}
            </button>
          </div>
          {reported ? (
            <span className="small muted">{t("reported")}</span>
          ) : (
            <button className="btn ghost" onClick={report} title={t("rules")}>
              ⚑ {t("report")}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

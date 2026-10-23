import { useState, type FormEvent } from "react";
import { Link, useSearchParams } from "react-router-dom";
import { AddressPicker, type Draft } from "../components/AddressPicker";
import { Stamp } from "../components/Stamp";
import { addressText, decodeAddress, isComplete, type Address } from "../lib/address";
import { deliveryMinutes } from "../lib/delivery";
import { readSaved, save, useGeo } from "../lib/hooks";
import { duration, useT, type MsgKey } from "../lib/i18n";
import { checkBody, MAX_BODY, MAX_PEN_NAME } from "../lib/safety";
import { seal } from "../lib/seal";
import { STAMPS } from "../lib/stamps";
import { store } from "../lib/store";
import { mailboxPath } from "./Home";

const FROM_KEY = "thikana.from";
const SERVER_ERRORS = ["empty", "too_long", "phone", "email", "link", "readonly", "full"];

export function Write() {
  const { t, lang } = useT();
  const geo = useGeo();
  const [params] = useSearchParams();
  const replyTo = params.get("reply");

  const [to, setTo] = useState<Draft>(() => decodeAddress(params.get("to")) ?? {});
  const [from, setFrom] = useState<Draft>(() => readSaved<Draft>(FROM_KEY) ?? {});
  const [penName, setPenName] = useState(() => readSaved<string>("thikana.penName") ?? "");
  const [body, setBody] = useState("");
  const [stamp, setStamp] = useState(STAMPS[0].id);
  const [sealed, setSealed] = useState(false);
  const [secret, setSecret] = useState("");
  const [hint, setHint] = useState("");
  const [error, setError] = useState<MsgKey | null>(null);
  const [busy, setBusy] = useState(false);
  const [sent, setSent] = useState<{ to: Address; minutes: number } | null>(null);

  if (!geo) return <p className="muted">…</p>;

  const minutes = isComplete(to) && isComplete(from) ? deliveryMinutes(geo, from.districtId, to.districtId) : null;

  async function submit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    if (!isComplete(to) || !isComplete(from)) return setError("err_address");
    const issue = checkBody(body);
    if (issue && !(sealed && issue !== "empty" && issue !== "too_long")) return setError(`err_${issue}` as MsgKey);
    if (sealed && secret.trim().length < 4) return setError("err_secret");

    setBusy(true);
    try {
      const payload = sealed ? await seal(body.trim(), secret) : body.trim();
      const res = await store.send({
        to,
        from,
        penName: penName.trim(),
        body: payload,
        stamp,
        sealed,
        sealHint: sealed ? hint.trim() : "",
        replyTo,
      });
      save(FROM_KEY, from);
      save("thikana.penName", penName.trim());
      setSent({ to, minutes: (new Date(res.deliverAt).getTime() - Date.now()) / 60_000 });
    } catch (err) {
      const msg = err instanceof Error ? err.message : "";
      setError(SERVER_ERRORS.includes(msg) ? (`err_${msg}` as MsgKey) : "err_generic");
    } finally {
      setBusy(false);
    }
  }

  if (sent) {
    return (
      <section className="card sent">
        <div className="big">📬</div>
        <h1>{t("sent")}</h1>
        <p className="address" style={{ display: "inline-block", textAlign: "left" }}>
          {addressText(sent.to, geo, lang)}
        </p>
        <p>
          🚚 {t("willArrive")} <strong>{duration(lang, sent.minutes)}</strong>
        </p>
        <div className="row" style={{ justifyContent: "center" }}>
          <Link className="btn" to={mailboxPath(sent.to)}>
            {t("goMailbox")}
          </Link>
          <Link className="btn ghost" to="/write">
            {t("writeAnother")}
          </Link>
        </div>
      </section>
    );
  }

  return (
    <form className="card" onSubmit={submit}>
      <h1>✍️ {t("writeLetter")}</h1>

      <fieldset>
        <legend>{t("to")}</legend>
        <AddressPicker geo={geo} value={to} onChange={setTo} />
      </fieldset>

      <fieldset>
        <legend>{t("from")}</legend>
        <p className="small muted" style={{ marginTop: 0 }}>
          {t("fromHint")}
        </p>
        <AddressPicker geo={geo} value={from} onChange={setFrom} />
        <div style={{ marginTop: 12 }}>
          <label htmlFor="pen">{t("penName")}</label>
          <input
            id="pen"
            maxLength={MAX_PEN_NAME}
            placeholder={t("penNamePlaceholder")}
            value={penName}
            onChange={(e) => setPenName(e.target.value)}
          />
        </div>
      </fieldset>

      <label htmlFor="body">{t("letter")}</label>
      <textarea
        id="body"
        className="paper"
        maxLength={MAX_BODY}
        placeholder={t("letterPlaceholder")}
        value={body}
        onChange={(e) => setBody(e.target.value)}
      />
      <p className="small muted">{t("rules")}</p>

      <label>{t("stamp")}</label>
      <div className="stamps" role="group" aria-label={t("stamp")}>
        {STAMPS.map((s) => (
          <button type="button" key={s.id} aria-pressed={stamp === s.id} onClick={() => setStamp(s.id)}>
            <Stamp id={s.id} />
          </button>
        ))}
      </div>

      <label className="row" style={{ margin: "14px 0", color: "var(--ink)" }}>
        <input type="checkbox" checked={sealed} onChange={(e) => setSealed(e.target.checked)} style={{ width: "auto" }} />
        {t("sealIt")}
      </label>
      {sealed && (
        <div className="grid" style={{ marginBottom: 12 }}>
          <div>
            <label htmlFor="secret">{t("secret")}</label>
            <input id="secret" value={secret} onChange={(e) => setSecret(e.target.value)} autoComplete="off" />
          </div>
          <div>
            <label htmlFor="hint">{t("hint")}</label>
            <input
              id="hint"
              maxLength={80}
              placeholder={t("hintPlaceholder")}
              value={hint}
              onChange={(e) => setHint(e.target.value)}
            />
          </div>
          <p className="small muted" style={{ gridColumn: "1 / -1", margin: 0 }}>
            {t("secretHint")}
          </p>
        </div>
      )}

      {minutes !== null && (
        <p className="small">
          🚚 {t("willArrive")} <strong>{duration(lang, minutes)}</strong>
        </p>
      )}
      <button className="btn" type="submit" disabled={busy}>
        {busy ? t("sending") : t("send")}
      </button>
      {error && <p className="error">{t(error)}</p>}
    </form>
  );
}

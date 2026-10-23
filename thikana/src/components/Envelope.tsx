import { addressLines } from "../lib/address";
import type { Geo } from "../lib/geo";
import { dateText, duration, useT } from "../lib/i18n";
import type { Letter } from "../lib/store";
import { Stamp } from "./Stamp";

interface Props {
  geo: Geo;
  letter: Letter;
  now: number;
  /** Show the destination instead of the sender (used in the public feed). */
  showTo?: boolean;
  onOpen: () => void;
}

export function Envelope({ geo, letter, now, showTo, onOpen }: Props) {
  const { t, lang } = useT();
  const from = addressLines(letter.from, geo, lang);
  const to = addressLines(letter.to, geo, lang);
  const left = (new Date(letter.deliverAt).getTime() - now) / 60_000;
  const inTransit = letter.body === null || left > 0;

  return (
    <button className="envelope" onClick={onOpen} disabled={inTransit}>
      <Stamp id={letter.stamp} />
      <div className="meta">
        <div className="who">{letter.penName || t("anonymous")}</div>
        <div className="small muted">
          {showTo ? (
            <>
              {from.village}, {from.district} → <strong>{to.village}</strong>, {to.district}
            </>
          ) : (
            <>
              {t("from_")}: {from.village}, {from.union}, {from.district}
            </>
          )}
        </div>
        {inTransit ? (
          <span className="badge transit">
            🚚 {t("inTransit")} · {t("arrivesIn")} {duration(lang, left)}
          </span>
        ) : letter.sealed ? (
          <span className="badge">🔒 {t("sealed")}</span>
        ) : (
          <div className="preview">{letter.body}</div>
        )}
      </div>
      <div className="postmark" aria-hidden>
        <strong>{from.district}</strong>
        {dateText(lang, letter.createdAt)}
      </div>
    </button>
  );
}

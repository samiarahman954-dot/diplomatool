import type { CSSProperties } from "react";
import { useT } from "../lib/i18n";
import { stampById } from "../lib/stamps";

export function Stamp({ id }: { id: string }) {
  const { lang } = useT();
  const s = stampById(id);
  return (
    <span className="stamp" style={{ "--c": s.color } as CSSProperties} aria-label={s[lang]}>
      <span aria-hidden>{s.icon}</span>
      {s[lang]}
    </span>
  );
}

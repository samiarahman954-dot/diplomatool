import { useEffect, useId, useState } from "react";
import { unionKey, type Address } from "../lib/address";
import type { Geo } from "../lib/geo";
import { useT } from "../lib/i18n";
import { store, type KnownVillage } from "../lib/store";

const OTHER = "__other";

export type Draft = Partial<Address>;

interface Props {
  geo: Geo;
  value: Draft;
  onChange: (next: Draft) => void;
}

export function AddressPicker({ geo, value, onChange }: Props) {
  const { t, lang } = useT();
  const id = useId();
  const [otherUnion, setOtherUnion] = useState(Boolean(value.upazilaId && !value.unionId && value.unionName));
  const [known, setKnown] = useState<KnownVillage[]>([]);

  const byName = <T extends { en: string; bn: string }>(xs: T[]) =>
    [...xs].sort((a, b) => a[lang].localeCompare(b[lang], lang));

  const districts = byName(geo.districts);
  const upazilas = value.districtId ? byName(geo.upazilasOf(value.districtId)) : [];
  const unions = value.upazilaId ? byName(geo.unionsOf(value.upazilaId)) : [];

  const unionReady = Boolean(value.districtId && value.upazilaId && (value.unionId || value.unionName?.trim()));
  const ukey = unionReady
    ? unionKey({
        districtId: value.districtId!,
        upazilaId: value.upazilaId!,
        unionId: value.unionId ?? "",
        unionName: value.unionName ?? "",
      })
    : "";

  // Suggest villages that already have mailboxes, so spellings converge.
  useEffect(() => {
    if (!ukey) return setKnown([]);
    let live = true;
    const timer = setTimeout(() => {
      store
        .villages(ukey)
        .then((v) => live && setKnown(v))
        .catch(() => live && setKnown([]));
    }, 250);
    return () => {
      live = false;
      clearTimeout(timer);
    };
  }, [ukey]);

  return (
    <div className="grid">
      <div>
        <label htmlFor={`${id}-d`}>{t("district")}</label>
        <select
          id={`${id}-d`}
          value={value.districtId ?? ""}
          onChange={(e) => {
            setOtherUnion(false);
            onChange({ districtId: e.target.value, village: value.village });
          }}
        >
          <option value="">{t("choose")}</option>
          {districts.map((d) => (
            <option key={d.id} value={d.id}>
              {d[lang]}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label htmlFor={`${id}-u`}>{t("upazila")}</label>
        <select
          id={`${id}-u`}
          value={value.upazilaId ?? ""}
          disabled={!value.districtId}
          onChange={(e) => {
            setOtherUnion(false);
            onChange({ districtId: value.districtId, upazilaId: e.target.value, village: value.village });
          }}
        >
          <option value="">{t("choose")}</option>
          {upazilas.map((u) => (
            <option key={u.id} value={u.id}>
              {u[lang]}
            </option>
          ))}
        </select>
      </div>
      <div>
        <label htmlFor={`${id}-n`}>{t("union")}</label>
        <select
          id={`${id}-n`}
          value={otherUnion ? OTHER : (value.unionId ?? "")}
          disabled={!value.upazilaId}
          onChange={(e) => {
            const other = e.target.value === OTHER;
            setOtherUnion(other);
            onChange({ ...value, unionId: other ? "" : e.target.value, unionName: "" });
          }}
        >
          <option value="">{t("choose")}</option>
          {unions.map((u) => (
            <option key={u.id} value={u.id}>
              {u[lang]}
            </option>
          ))}
          <option value={OTHER}>{t("otherUnion")}</option>
        </select>
        {otherUnion && (
          <input
            aria-label={t("union")}
            style={{ marginTop: 8 }}
            placeholder={t("unionPlaceholder")}
            maxLength={60}
            value={value.unionName ?? ""}
            onChange={(e) => onChange({ ...value, unionId: "", unionName: e.target.value })}
          />
        )}
      </div>
      <div>
        <label htmlFor={`${id}-v`}>{t("village")}</label>
        <input
          id={`${id}-v`}
          list={`${id}-vl`}
          placeholder={t("villagePlaceholder")}
          maxLength={60}
          disabled={!unionReady}
          value={value.village ?? ""}
          onChange={(e) => onChange({ ...value, village: e.target.value })}
        />
        <datalist id={`${id}-vl`}>
          {known.map((k) => (
            <option key={k.key} value={k.address.village} />
          ))}
        </datalist>
      </div>
    </div>
  );
}

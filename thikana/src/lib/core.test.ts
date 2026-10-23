import { describe, expect, it } from "vitest";
import raw from "../data/bd-geo.json";
import { addressKey, decodeAddress, encodeAddress, slug, type Address } from "./address";
import { deliveryMinutes } from "./delivery";
import { buildGeo } from "./geo";
import { duration } from "./i18n";
import { checkBody } from "./safety";
import { seal, unseal } from "./seal";

const geo = buildGeo(raw);
const find = <T extends { en: string }>(xs: T[], en: string) => xs.find((x) => x.en === en)!;

const kushtia = find(geo.districts, "Kushtia");
const daulatpur = find(geo.upazilasOf(kushtia.id), "Daulatpur");
const philipnagar = find(geo.unionsOf(daulatpur.id), "Philipnagor");
const natore = find(geo.districts, "Natore");
const lalpur = find(geo.upazilasOf(natore.id), "Lalpur");

const islampur: Address = {
  districtId: kushtia.id,
  upazilaId: daulatpur.id,
  unionId: philipnagar.id,
  unionName: "",
  village: "Islampur",
};
const bagatupara: Address = {
  districtId: natore.id,
  upazilaId: lalpur.id,
  unionId: "",
  unionName: "Gopalpur Pourashava",
  village: "Bagatu para",
};

describe("addresses", () => {
  it("merges spelling variants into one mailbox", () => {
    expect(slug("Bagatu para")).toBe(slug("bagatupara"));
    expect(slug("Bagatu-Para")).toBe("bagatupara");
    expect(slug("ইসলাম পুর")).toBe("ইসলামপুর");
    expect(addressKey({ ...bagatupara, village: "BAGATUPARA" })).toBe(addressKey(bagatupara));
  });

  it("builds keys from dataset ids and typed-in pourashavas", () => {
    expect(addressKey(islampur)).toBe(`bd.${kushtia.id}.${daulatpur.id}.u${philipnagar.id}.islampur`);
    expect(addressKey(bagatupara)).toBe(`bd.${natore.id}.${lalpur.id}.xgopalpurpourashava.bagatupara`);
  });

  it("round-trips through the URL encoding", () => {
    expect(decodeAddress(encodeAddress(bagatupara))).toEqual(bagatupara);
    expect(decodeAddress("garbage")).toBeNull();
  });
});

describe("delivery", () => {
  it("takes about an hour from Kushtia to Natore", () => {
    const m = deliveryMinutes(geo, kushtia.id, natore.id);
    expect(m).toBeGreaterThan(45);
    expect(m).toBeLessThan(90);
  });

  it("takes only the handling time within a district", () => {
    expect(deliveryMinutes(geo, kushtia.id, kushtia.id)).toBe(5);
  });

  it("formats durations in Bangla and English", () => {
    expect(duration("en", 64)).toBe("1 h 4 min");
    expect(duration("bn", 64)).toBe("১ ঘণ্টা ৪ মিনিট");
    expect(duration("en", 1500)).toBe("1 d 1 h");
  });
});

describe("safety", () => {
  it("allows ordinary letters, dates and years", () => {
    expect(checkBody("Eid Mubarak! Dekha hobe 23-10-2026 e, 2026 sal e.")).toBeNull();
  });

  it("blocks phone numbers in any form", () => {
    expect(checkBody("call 01712345678")).toBe("phone");
    expect(checkBody("call 017-1234 5678")).toBe("phone");
    expect(checkBody("+8801712345678")).toBe("phone");
    expect(checkBody("ফোন ০১৭১২৩৪৫৬৭৮")).toBe("phone");
    expect(checkBody("+44 7911 123456")).toBe("phone");
  });

  it("blocks emails and links", () => {
    expect(checkBody("mail me a@b.com")).toBe("email");
    expect(checkBody("see facebook.com/x")).toBe("link");
  });

  it("rejects empty letters", () => {
    expect(checkBody("   ")).toBe("empty");
  });
});

describe("seal", () => {
  it("opens only with the right secret", async () => {
    const box = await seal("গোপন কথা", "amader school");
    expect(box.startsWith("seal1.")).toBe(true);
    expect(box).not.toContain("গোপন");
    expect(await unseal(box, "amader school")).toBe("গোপন কথা");
    expect(await unseal(box, "wrong")).toBeNull();
  });
});

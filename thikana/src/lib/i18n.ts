import { createContext, useContext } from "react";

export type Lang = "bn" | "en";

const dict = {
  appName: { bn: "ঠিকানা", en: "Thikana" },
  tagline: { bn: "মানুষকে না, ঠিকানায় চিঠি লিখুন", en: "Write to an address, not a person" },
  intro: {
    bn: "যেকোনো গ্রামের ঠিকানায় চিঠি পাঠান। সেই ঠিকানার ডাকবাক্স খুললে যে কেউ পড়তে পারবে। চিঠি পৌঁছাতে দূরত্ব অনুযায়ী সময় লাগে, ঠিক আসল চিঠির মতো।",
    en: "Send a letter to any village. Anyone who opens that address's mailbox can read it. Letters take time to travel, depending on distance, just like real post.",
  },
  writeLetter: { bn: "চিঠি লিখুন", en: "Write a letter" },
  openMailbox: { bn: "ডাকবাক্স খুলুন", en: "Open mailbox" },
  findMailbox: { bn: "একটি ঠিকানার ডাকবাক্স খুঁজুন", en: "Find an address's mailbox" },
  randomVillage: { bn: "অচেনা গ্রামে ঘুরে আসুন", en: "Visit a random village" },
  noRandom: { bn: "এখনো কোনো ডাকবাক্সে চিঠি নেই। প্রথম চিঠিটা আপনিই লিখুন!", en: "No mailbox has letters yet. Be the first to write!" },
  recent: { bn: "সদ্য পৌঁছানো চিঠি", en: "Recently delivered" },
  noRecent: { bn: "এখনো কোনো চিঠি পৌঁছায়নি।", en: "No letters delivered yet." },
  district: { bn: "জেলা", en: "District" },
  upazila: { bn: "উপজেলা", en: "Upazila" },
  union: { bn: "ইউনিয়ন / পৌরসভা", en: "Union / Pourashava" },
  village: { bn: "গ্রাম / মহল্লা", en: "Village / Mohalla" },
  choose: { bn: "বেছে নিন", en: "Choose" },
  otherUnion: { bn: "অন্য / পৌরসভা (নিজে লিখুন)", en: "Other / Pourashava (type it)" },
  unionPlaceholder: { bn: "যেমন: গোপালপুর পৌরসভা", en: "e.g. Gopalpur Pourashava" },
  villagePlaceholder: { bn: "যেমন: ইসলামপুর", en: "e.g. Islampur" },
  to: { bn: "প্রাপকের ঠিকানা", en: "To (address)" },
  from: { bn: "প্রেরকের ঠিকানা", en: "From (your address)" },
  fromHint: {
    bn: "উত্তর এই ঠিকানার ডাকবাক্সে যাবে। শুধু গ্রাম পর্যন্ত, বাড়ির নম্বর না।",
    en: "Replies go to this address's mailbox. Village level only, never a house.",
  },
  penName: { bn: "ছদ্মনাম (ঐচ্ছিক)", en: "Pen name (optional)" },
  penNamePlaceholder: { bn: "যেমন: এক প্রবাসী ভাই", en: "e.g. A friend far away" },
  anonymous: { bn: "অজ্ঞাত", en: "Anonymous" },
  letter: { bn: "চিঠি", en: "Letter" },
  letterPlaceholder: { bn: "প্রিয় ইসলামপুরবাসী,\n\n", en: "Dear people of Islampur,\n\n" },
  stamp: { bn: "ডাকটিকিট", en: "Stamp" },
  sealIt: { bn: "🔒 সিলমোহর করুন (গোপন শব্দ ছাড়া কেউ পড়তে পারবে না)", en: "🔒 Seal it (only readable with a secret word)" },
  secret: { bn: "গোপন শব্দ", en: "Secret word" },
  secretHint: {
    bn: "শব্দটা প্রাপককে আলাদাভাবে জানান। আমরাও এটা জানি না, ভুলে গেলে চিঠি আর খোলা যাবে না।",
    en: "Tell the reader separately. We never see it, so a forgotten word means the letter stays sealed.",
  },
  hint: { bn: "ইঙ্গিত (সবাই দেখবে)", en: "Hint (public)" },
  hintPlaceholder: { bn: "যেমন: আমাদের স্কুলের নাম", en: "e.g. our school's name" },
  send: { bn: "ডাকে দিন ✉️", en: "Post it ✉️" },
  sending: { bn: "পাঠানো হচ্ছে…", en: "Posting…" },
  willArrive: { bn: "পৌঁছাতে সময় লাগবে প্রায়", en: "Estimated travel time" },
  sent: { bn: "চিঠি ডাকে দেওয়া হয়েছে!", en: "Your letter is on its way!" },
  writeAnother: { bn: "আরেকটা চিঠি লিখুন", en: "Write another" },
  goMailbox: { bn: "প্রাপকের ডাকবাক্স দেখুন", en: "See the recipient's mailbox" },
  mailboxOf: { bn: "ডাকবাক্স", en: "Mailbox" },
  writeHere: { bn: "এই ঠিকানায় চিঠি লিখুন", en: "Write to this address" },
  empty: { bn: "এই ডাকবাক্স এখনো খালি। প্রথম চিঠিটা আপনিই লিখুন!", en: "This mailbox is empty. Be the first to write!" },
  inTransit: { bn: "পথে আছে", en: "On the way" },
  arrivesIn: { bn: "পৌঁছাবে", en: "Arrives in" },
  sealed: { bn: "সিলমোহর করা", en: "Sealed" },
  open: { bn: "খুলুন", en: "Open" },
  unlock: { bn: "সিল খুলুন", en: "Break the seal" },
  wrongSecret: { bn: "গোপন শব্দ মেলেনি।", en: "That's not the secret word." },
  reply: { bn: "উত্তর দিন", en: "Reply" },
  report: { bn: "রিপোর্ট", en: "Report" },
  reported: { bn: "রিপোর্ট করা হয়েছে। ধন্যবাদ।", en: "Reported. Thank you." },
  close: { bn: "বন্ধ করুন", en: "Close" },
  from_: { bn: "প্রেরক", en: "From" },
  replyTo: { bn: "এই চিঠির উত্তর", en: "In reply to a letter" },
  demoMode: {
    bn: "ডেমো মোড: চিঠি শুধু এই ব্রাউজারে থাকছে। সবার সাথে শেয়ার করতে Supabase যুক্ত করুন (README দেখুন)।",
    en: "Demo mode: letters are stored in this browser only. Connect Supabase to share them (see README).",
  },
  rules: {
    bn: "নিয়ম: কারো নাম ধরে খারাপ কথা, ফোন নম্বর, ইমেইল বা লিংক নয়। ৩টি রিপোর্ট হলে চিঠি লুকিয়ে যায়।",
    en: "Rules: no attacks on named people, no phone numbers, emails or links. Letters hide after 3 reports.",
  },
  err_empty: { bn: "চিঠি খালি।", en: "The letter is empty." },
  err_too_long: { bn: "চিঠি অনেক লম্বা।", en: "The letter is too long." },
  err_phone: {
    bn: "ফোন/NID নম্বর দেওয়া যাবে না, ডাকবাক্স সবার জন্য খোলা। নম্বর দিতে চাইলে চিঠি সিলমোহর করুন।",
    en: "No phone/ID numbers: mailboxes are public. Seal the letter if you must share one.",
  },
  err_email: { bn: "ইমেইল দেওয়া যাবে না।", en: "No email addresses, please." },
  err_link: { bn: "লিংক দেওয়া যাবে না।", en: "No links, please." },
  err_address: { bn: "দুটো ঠিকানাই পুরো দিন।", en: "Please complete both addresses." },
  err_secret: { bn: "গোপন শব্দ অন্তত ৪ অক্ষরের দিন।", en: "The secret word needs at least 4 characters." },
  err_generic: { bn: "কিছু একটা ভুল হয়েছে।", en: "Something went wrong." },
  minutes: { bn: "মিনিট", en: "min" },
  hours: { bn: "ঘণ্টা", en: "h" },
  days: { bn: "দিন", en: "d" },
  lessThanMinute: { bn: "এক মিনিটের কম", en: "under a minute" },
} satisfies Record<string, Record<Lang, string>>;

export type MsgKey = keyof typeof dict;

export function translate(lang: Lang, key: MsgKey): string {
  return dict[key][lang];
}

const BN_DIGITS = "০১২৩৪৫৬৭৮৯";

export function num(lang: Lang, n: number | string): string {
  const s = String(n);
  return lang === "bn" ? s.replace(/\d/g, (d) => BN_DIGITS[Number(d)]) : s;
}

export function duration(lang: Lang, minutes: number): string {
  const m = Math.max(0, Math.round(minutes));
  if (m < 1) return translate(lang, "lessThanMinute");
  const d = Math.floor(m / 1440);
  const h = Math.floor((m % 1440) / 60);
  const mm = m % 60;
  const parts: string[] = [];
  if (d) parts.push(`${num(lang, d)} ${translate(lang, "days")}`);
  if (h) parts.push(`${num(lang, h)} ${translate(lang, "hours")}`);
  if (mm && !d) parts.push(`${num(lang, mm)} ${translate(lang, "minutes")}`);
  return parts.join(" ");
}

export function dateText(lang: Lang, iso: string): string {
  return new Date(iso).toLocaleDateString(lang === "bn" ? "bn-BD" : "en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

export const LangContext = createContext<{ lang: Lang; setLang: (l: Lang) => void }>({
  lang: "bn",
  setLang: () => {},
});

export function useT() {
  const { lang } = useContext(LangContext);
  return { lang, t: (key: MsgKey) => translate(lang, key) };
}

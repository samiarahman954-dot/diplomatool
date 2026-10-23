# 📮 ঠিকানা · Thikana

**মানুষকে না, ঠিকানায় চিঠি লিখুন।** Write to an address, not a person.

যেকোনো গ্রামের ঠিকানায় চিঠি পাঠান: জেলা → উপজেলা → ইউনিয়ন/পৌরসভা → গ্রাম।
সেই ঠিকানার ডাকবাক্স খুললে যে কেউ চিঠি পড়তে পারবে। দূরত্ব অনুযায়ী চিঠি পৌঁছাতে সময় লাগে, ঠিক আসল ডাকের মতো।

## What works today

- **Address picker** with all 64 districts, 494 upazilas and 4,540 unions of Bangladesh (Bangla + English names).
  Pourashavas and anything missing from the dataset can be typed in.
- **Village mailboxes**: one public mailbox per village. Spellings merge, so "Bagatu para" and
  "Bagatupara" land in the same mailbox, and villages that already exist are suggested as you type.
- **Slow delivery** 🚚: 5 minutes plus 1 minute per km between districts. Kushtia → Natore takes about an hour,
  Teknaf → Tetulia most of a day. The server sets the time, and a letter's text stays hidden until it arrives.
- **Sealed letters** 🔒: encrypted in the browser (PBKDF2 + AES-GCM) with a secret word the sender shares
  separately. The server stores only ciphertext.
- **Reply** sends a letter back to the sender's village mailbox.
- **Stamps, postmarks, ruled paper**, a random-village button and a "recently delivered" feed.
- **Safety**: open letters can't contain phone/NID numbers, emails or links (checked in the browser *and* the
  database). Letters hide after 3 reports from different people.
- **Bangla / English** toggle, light and dark themes, works on phones.

## Run it

```sh
cd thikana
npm install
npm run dev        # http://localhost:5173
npm test           # unit tests
npm run build      # static site in dist/
```

Without Supabase keys the app runs in **demo mode**: letters are saved only in your browser's localStorage.

## Make it public with Supabase (free tier is enough)

1. Create a project at [supabase.com](https://supabase.com).
2. In **SQL Editor**, run `supabase/schema.sql`, then `supabase/districts.sql`.
3. Copy `.env.example` to `.env` and fill in **Project URL** and **anon public key**
   (Settings → API).
4. `npm run dev`. The demo-mode banner disappears and letters are shared with everyone.

The anon key can only call the functions in `schema.sql`; row-level security keeps the tables themselves closed.

## Deploy

`npm run build` produces a static site (hash routing, relative paths). It works on Vercel, Netlify,
Cloudflare Pages or GitHub Pages. Set the two `VITE_SUPABASE_*` variables in the host's settings.

## Project layout

```
src/lib/address.ts     address model, mailbox keys, spelling normalisation
src/lib/delivery.ts    travel-time formula (mirrored in schema.sql)
src/lib/safety.ts      content rules (mirrored in schema.sql)
src/lib/seal.ts        sealed-letter encryption
src/lib/store/         Store interface + Supabase and localStorage backends
src/pages/             Home, Write, Mailbox
supabase/schema.sql    tables, row-level security, RPC functions
scripts/build-geo.mjs  regenerates src/data/bd-geo.json from nuhil/bangladesh-geocode
```

Geography data comes from [nuhil/bangladesh-geocode](https://github.com/nuhil/bangladesh-geocode).

## Known gaps

- No rate limiting yet. Add a Supabase Edge Function or captcha before a public launch.
- Reports are de-duplicated per browser, so a determined person could clear storage and report again.
- The same village written in Bangla and in English gives two mailboxes. Suggestions reduce this but don't fix it.
- Pourashavas and city wards aren't in the dataset yet, so people type them in.

## Roadmap ideas

- Map of Bangladesh with letters flying between villages
- Time-capsule letters ("open in 2030"), district stamp collections
- Union "postmaster" volunteers for moderation
- Other countries (OpenStreetMap / GeoNames admin boundaries) and diaspora mode
- Revenue later: stamp shop, express stamps, printed postcards, local notice-board ads

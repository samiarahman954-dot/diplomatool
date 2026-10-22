# Lead Finder

Client khojar tool. Kono shohor ar business type dile (jemon "Austin er dentist") tool ta:

1. Map (OpenStreetMap) theke oi business gulo khuje ber kore
2. Protiti business er website check kore: site down, SSL nai, mobile-friendly na, slow,
   purono WordPress, purono copyright year, SEO meta nai, analytics nai
3. Jar website e beshi problem, take beshi score dey (0-100). **Beshi score = bhalo client.**
4. Website theke ba contact page theke email ber kore
5. Protiti business er jonno oder nijer problem diye **personalized email** likhe dey
6. Shob kichu **HTML report** (browser e khulben) ar **CSV** (Excel / Google Sheets) e save kore

Website nai kintu phone number ache, emon business o list e ashe. Era "website banano" kaj er client.
Boro chain brand (McDonald's, Starbucks) baad dey, karon ora freelancer hire kore na.

## Web version (install chara, browser e)

https://raw.githack.com/samiarahman954-dot/diplomatool/ccr-3bce4458-ck8crm/lead-finder/index.html

Link ta khulun, list theke city (160+ shohor) ar business type (100+ dhoron) select korun. Select korlei
search shuru hoy. List e na thakle "Other city" select kore nam likhun. Kono install lagbe na, phone eo chole.
Website check hoy Google PageSpeed diye. Key chara Google alpo kichu check korte dey, tai ekta free
[PageSpeed API key](https://developers.google.com/speed/docs/insights/v5/get-started#APIKey) niye
"Your details & PageSpeed key" e ekbar boshiye din. Browser key ta mone rakhe, protibar dite hoy na.
Key ta code e rakha hoy na, karon repo public: code e thakle je keu niye use korte parto.

### Client ke convince korar feature

- **Client report / Proposal:** protiti lead er card e button ache. Report e thake: A-F grade, 4 ta Google
  score, phone e site er screenshot, second-by-second load howar chobi, problem gulo (ki khoti kore ar
  kivabe thik korben), ek-i shohorer competitor der sathe tulona, speed details, apnar 3 ta package
  (recommended ta highlight kora), guarantee ar contact. **Save as PDF** ba **Download HTML** kore pathan.
  Website chara business er jonno "Proposal" ase: kon competitor der website ache, ar website e ki thakbe.
- **Email sequence:** First email (free report offer, kono link/attachment chara), Follow-up (3 din pore),
  Last follow-up (7 din pore), Send report (reply dile, PDF attach kore).
- **Your details, prices:** naam, title, email, WhatsApp, portfolio, call booking link, package er dam ar
  guarantee. Report ar email e eguloi boshe, browser mone rakhe.

### Redesign bikrir feature

- **"Keno customer ashche na" checklist:** customer ana website e je 8 ta jinish thake (mobile, 3 second e
  load, HTTPS, Call/Book button, tap-to-call, contact form, review, Google setup), tar kon gulo client er
  site e nai, ✓/✗ diye dekhay. Report er prothom page ei ta: "Your website isn't turning visitors into
  customers".
- **Site er content pora:** website check er por tool client er site theke heading, description, about
  text, logo, chobi, email, brand color ar copyright year pore. Browser shorasori onno site porte dey na,
  tai public proxy (allorigins, corsproxy, r.jina.ai) diye pore. Kaj na korle industry er default lekha
  use hoy.
- **Claude redesign:** card er **Redesign with Claude** button e Claude (Claude Opus 5.5) client er jonno
  notun homepage design kore. Template na: Claude dekhe client er current site er pura screenshot, site er
  lekha, chobi, logo, brand color, map er tottho (phone, address, opening hours) ar test e pawa problem,
  tarpor oder nijer content diye ekdom notun page likhe. Nokol review, award, dam ityadi banay na; ja nai
  ta [bracket] e placeholder hishebe rakhe. "Extra instructions" e nijer kotha likhte paren (jemon "premium
  feel"). **Download homepage** e alada HTML file paben.
  - Ekta **Claude API key** lage (https://console.anthropic.com/settings/keys). Key ta "Your details" ba
    design screen e ekbar din, browser mone rakhe, shorasori Anthropic e jay.
  - Protiti design apnar Anthropic account theke ~$0.20-0.50 khoroch hoy, 1-3 minute lage.
  - Design ta browser e save thake (protiti business er jonno), page bondho korleo hariye jay na.
  - Anthropic er official SDK (`vendor/anthropic-sdk-0.132.0.js`, MIT) repo te bundle kora, alada kichu
    install lagbe na.
- Report e **Now bonam After the redesign** (phone ar desktop) thake. PDF e eta nijer page e ase.
- Prothom email e free report + free homepage design offer kora hoy.

Screenshot gulo browser e save hoy na (onek boro). Page bondho kore abar khulle report e
**Re-check for screenshots** chapun.

Web version copyright year, WordPress version ar website theke email khujte pare na (browser er
security niyom er karone). Eguloo lagle niche Python version chalan.

## Python version: ki lagbe

Shudhu **Python 3.8 ba notun**. Kono `pip install` lagbe na.

- Windows: https://www.python.org/downloads/ theke install korun, install er somoy
  "Add Python to PATH" tick diben.
- Check: `python --version`

## Kivabe chalaben

`lead-finder` folder e terminal (Windows e Command Prompt) khulun.

### 1. Shohor + business type diye khojen

```
python lead_finder.py search --city "Austin, Texas, USA" --category dentist --your-name "Rahim Uddin" --your-site "https://rahim.dev"
```

Kaj shesh hole `leads/dentist_austin-texas-usa.html` file ta browser e khulun.
Protiti lead e **Email draft** e click korle email ta dekhben. **Copy email** ba
**Open in mail app** button ache.

Shob category dekhte:

```
python lead_finder.py categories
```

`dentist, lawyer, plumber, electrician, hvac, roofer, real_estate, restaurant, salon, gym,
car_repair, accountant, veterinary, hotel` ityadi ache. List e na thakle OpenStreetMap tag
diye khujte paren: `--tag shop=bicycle`

### 2. Nijer jogar kora website list check korun

Google Maps theke website gulo copy kore ekta `sites.txt` file e rakhun, protiti line e ekta:

```
Smile Dental | https://smiledental.com
Bob's Plumbing | bobsplumbing.co.uk
anotherbusiness.com
```

Tarpor:

```
python lead_finder.py audit --input sites.txt --city "Austin" --category-label "dentists" --your-name "Rahim Uddin"
```

`name, website, phone, email` column wala CSV file o dite paren.

## Kaje lage emon option

| Option | Ki kore |
|---|---|
| `--min-score 40` | 40 er kom score er lead lukiye rakhe |
| `--skip-no-website` | Jader website nai tader baad dey |
| `--limit 300` | Koto gulo business khujbe (default 150) |
| `--include-chains` | Boro brand o rakhe |
| `--psi-key KEY` | Google PageSpeed er mobile speed score o yog kore (free key: https://developers.google.com/speed/docs/insights/v5/get-started). Proti site e 20-40 second beshi lagbe. |
| `--contact-email you@mail.com` | OpenStreetMap er niyom onujayi nijer email din |
| `--out folder/name` | Output kothay save hobe |

Protibar naam likhte na chaile environment variable set korte paren:
`LEAD_YOUR_NAME`, `LEAD_YOUR_SITE`, `LEAD_CONTACT_EMAIL`, `PSI_API_KEY`.

## Score kivabe hoy

| Problem | Point |
|---|---|
| Website down / khule na | 80 |
| Website e nai (phone ache) | 70 |
| SSL certificate nosto | 35 |
| HTTPS nai ("Not Secure") | 30 |
| Mobile-friendly na | 30 |
| PageSpeed mobile score 50 er niche | 25 |
| 3 second er beshi load time | 20 |
| WordPress 6.0 er ager version | 20 |
| Copyright 2 bochor ba ar beshi purono | 15 |
| Purono jQuery | 10 |
| Page title nai | 10 |
| Meta description nai | 8 |
| Analytics nai | 5 |

**60+ = hot**, 30-59 = warm. Hot lead gulo age contact korun.

## Bhalo result er tips

- **Email pathanor age nije site ta ekbar khule dekhen.** Tool bhul korte pare (jemon kono site
  JavaScript diye copyright year dekhay).
- Draft ta hubohu pathaben na. 1 line nijer moto kore yog korun, jemon oder kono service er naam.
- Hot lead e 2 minute er Loom video record kore link ta email e din. Reply onek beshi ase.
- Din e 20-30 tar beshi email pathaben na, noile spam e chole jabe. Nijer domain er email
  (jemon `rahim@rahim.dev`) use korun, Gmail na.
- Keu "no" bolle ar pathaben na. Email er niche nijer naam, website thakbe. US, UK, EU er
  anti-spam niyom (CAN-SPAM, GDPR) mene cholun.
- Email na pele: phone korun, website er contact form use korun, ba LinkedIn e owner ke khujen.

## Data source

Business er list ase [OpenStreetMap](https://www.openstreetmap.org) theke (free, kono API key lage na).
Boro shohore (US, UK, Europe, Australia) data bhalo. Kono jaygay kom business pele Google Maps theke
website gulo copy kore `audit` mode use korun.

## Test

```
python -m unittest test_lead_finder
```

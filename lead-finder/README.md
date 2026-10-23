# Lead Finder

Client khojar tool. Kono shohor ar business type dile (jemon "Austin er dentist") tool ta:

1. Google Maps theke oi business gulo khuje ber kore (Python version e OpenStreetMap)
2. Protiti business er website check kore: site down, SSL nai, mobile-friendly na, slow,
   purono WordPress, purono copyright year, SEO meta nai, analytics nai
3. Jar website e beshi problem, take beshi score dey (0-100). **Beshi score = bhalo client.**
4. Website theke ba contact page theke email ber kore
5. Protiti business er jonno oder nijer problem diye **personalized email** likhe dey
6. Shob kichu **HTML report** (browser e khulben) ar **CSV** (Excel / Google Sheets) e save kore

Default e shudhu **website ache emon business** ashe (form e **Only with a website**), karon website
nai emon business er beshir bhag e email/contact pawa jay na. Tik tule dile tarao ashe.
Boro chain brand (McDonald's, Starbucks) baad dey, karon ora freelancer hire kore na.

## Web version (install chara, browser e)

https://raw.githack.com/samiarahman954-dot/diplomatool/ccr-3bce4458-ck8crm/lead-finder/index.html

List theke city (160+ shohor) ar business type (100+ dhoron) select korun, search nijei shuru hoy.
Kono install lagbe na, phone eo chole. **Settings** e:

- **Google PageSpeed API key:** website speed test er jonno
  ([free key](https://developers.google.com/speed/docs/insights/v5/get-started#APIKey)). Browser mone rakhe.
- **Google Maps API key (lagbei):** shob business Google Maps theke ase, phone, website, opening hours ar
  rating soho. Key chara search hoy na. Google Cloud e key tar jonno **Maps JavaScript API** ar **Places API (New)** chalu korun. Key ta
  **Application restrictions > Websites** e `https://raw.githack.com/samiarahman954-dot/*` diye restrict
  korun, ar Quotas e daily limit din, jate keu key churi korleo bill na hoy. Key code e nai, shudhu apnar
  browser e thake.
- **Email signature (optional):** shob email ar report er shesh e boshe.

Dam, package ba guarantee tool e nai. Protiti client er dam alada, tai oigulo email e nijer moto likhben.

### Jader website ache: report pathan

1. Tool site ta check kore: speed, mobile, security, Google, ar design er problem (chobi stretch/blurry,
   lekha porte koshto, page laaphay, purono copyright), sathe customer ana jinish gulo (Call/Book button,
   tap-to-call, form, review).
2. **Email** > "First email": 3 ta ashol problem + free report er offer (link/attachment chara).
3. Reply dile **Report** khulun, tarpor **Email this report** (PDF nijei attach hoy) ba **Save as PDF**. Report ta ekjon designer er
   review er moto lekha, kono speed table ba score er bhir nai:
   - shuru: "I looked at your site the way a new customer would..." (faster competitor thakle tar tulona)
   - **What a new customer sees:** oder homepage er phone screenshot, problem er jaygay laal box ar number
     (faint lekha, chhoto button, blurry/stretched chobi, deri te ashe emon chobi), pashe top 8 problem
   - **The problems, one by one:** protitar "What I saw" (oder site er ashol lekha uddhrito kore, jemon
     headline "Welcome to...") ar "Why it costs you customers"
   - **Also holding the site back:** baki gulo ek line kore, technical jinish ek line e "Behind the scenes"
   - **What a redesign changes:** Now > After the redesign table
   - shesh e offer: "See your new homepage first, for free"
   Design analysis e ja dhore: mobile layout, "Not Secure", speed, headline kichu bole na, shohorer naam nai,
   Call/Book button nai, tap-to-call nai, form nai, review nai, menu te 8+ item, lekhar deyal, slider,
   purono dhoroner code, chhoto button, faint lekha, blurry/stretched chobi, page laaphay, purono copyright.
4. Client "yes" bolle **Redesign prompt**: screenshot download korun, prompt copy kore
   [Claude](https://claude.ai/new) e paste korun, screenshot attach korun. Prompt e oder site er pura lekha,
   chobi, logo, brand color, map er tottho ar shob problem thake. Claude shesh e "main changes" er list dey,
   seta "Send sample redesign" email e boshan.

**Report er notun jinish:**
- **Your website at a glance:** 6 ta bhag (design, phone, speed, trust, customer ana, Google/share) e Poor /
  Needs work / Good, client ek nojore bujhe.
- **Looks like a website from around 2016:** site er code theke boyosh dhore (purono jQuery, Bootstrap 3,
  Font Awesome 4, Internet Explorer 8 er code, Flash, purono WordPress theme/version, purono copyright).
  Eta report er 1 number problem, karon "patch" kore eta thik hoy na, redesign lage.
- Stock photo, map/directions nai, opening hours nai, Facebook/WhatsApp e share korle chobi ashe na.
- **Computer er screenshot** o (phone er pashe).
- **"In short" box:** report er shurutei ek line e pura case.
- **How your site compares with a modern website:** 15 ta jinish ja ekhon customer ra ekta business
  website e ashha kore (phone-first, 2.5 second e load, Call/Book button, booking form, review, ashol chobi,
  map/hours, share preview…), ✓/✕ diye, "Yours has 4 of 15". Eta e "website modern na" er proman.
- **Why patching won't be enough:** Google rating diye "manush apnake khoje, kintu website reputation er
  biruddhe kaj kore", tarpor "Patching vs new website" table.
- Phone e homepage koto screen lomba (12+ hole problem).
- **Google er nijer "good" standard e bichar:** speed 90 er niche hole "Slower than Google's standard"
  (Google nije 50-89 ke "needs improvement" bole), accessibility/SEO/best practices 90 er niche, page
  laphano 0.1 er beshi, chobi optimise na (Google koto KB bachano jay bole), brand font nai.
- **Verdict, point by point** (report er shurute, proman thakle tobei):
  "Your website looks old." / "The design is generic." / "It isn't a modern website." / "It's hard to use on a
  phone." / "It's slow." / "It has security problems." / "It doesn't turn visitors into customers."
- **What's working:** sorbochcho 3 ta bhalo jinish ek line e, baki shob problem er dike focus.
- **What a modern website has, and yours doesn't:** shudhu ja nai seta dekhay ("missing 12 of 19").
- **Generic design:** Divi, Avada, Astra, Elementor, Wix, Squarespace, GoDaddy builder ityadi template dhore;
  sathe standard font/stock photo/purono look thakle "A generic, template look".
- **Security check:** HTTPS, certificate, insecure content, CVE number soho vulnerable library (jQuery, Bootstrap,
  jQuery UI, Moment, Lodash, Handlebars, AngularJS), purono WordPress, Mozilla Observatory er security
  header grade (A+ to F), page er code e error. "Fails 5 of 7 basic security checks".
- **Competitor ranking:** ek search e 4+ site check hole, ar ei site nicher ordhek e thakle intro e:
  "I checked 12 dentist websites in Austin; on Google's mobile speed test yours ranks 10 of 12."
- **Design notes:** report e "My notes on the design" section. Nije likhun, ba **Download screenshots** +
  **Copy design review prompt** diye Claude ke dekhan, Claude er uttor paste korun. Eta e "font/colour/layout
  purono" er moto chokhe dekha jinish ashe, ja code theke dhora jay na.
- **Edit text:** report er je kono lekha click kore bodlan, tarpor Save as PDF.
- Google er phone screenshot dhushor/faka ashle (loading screen ba video thakle hoy) tool seta baad dey.
  Tokhon phone e nijer screenshot niye **Add your own screenshot** chapun (lomba chobi = phone, choura = computer).

### Jader website nai: design pathan

1. **Website prompt** copy kore Claude e paste korun. Prompt e business er naam, dhoron, shohor, address,
   phone, opening hours thake. Claude nokol review/award banay na, [bracket] e placeholder rakhe.
2. **Email** > "First email": free website design er offer.
3. Reply dile design er screenshot ba link "Send design" email e pathan.

### Contact: email, WhatsApp, Messenger

**Email** button shob card e thake. Email jana na thakle **Open website** diye oder contact page e email
khujun, tarpor **Email** chepe To te likhe din: tool ta mone rakhe.

Protiti card er **Contact** row:

- **Gmail:** Gmail e notun email khule, to/subject/lekha boshano. **Mail app:** computer/phone er email app e.
  **Copy address:** email address copy.
- **WhatsApp:** number e WhatsApp chat khule, chhoto message likha thake. Number antorjatik format e nijei
  hoy (jemon Dhaka er `01711...` > `8801711...`). Map listing e WhatsApp number dewa thakle
  "WhatsApp (listed)" dekhay. Number dekhte landline hole "WhatsApp (landline?)" dekhay.
- **Messenger** (Facebook page thakle), **Instagram DM** (Instagram thakle), **Contact form** (site e contact
  page pele): message copy hoy, page khule, apni paste koren.
- **Copy message:** chhoto message ta copy.

Kono number e WhatsApp ache kina **age theke check korar public upay nai** (WhatsApp eta shudhu paid Business
API te dey). WhatsApp chaple number e WhatsApp na thakle WhatsApp nijei bole dey; tokhon email ba Messenger use korun.
Unknown number theke beshi WhatsApp message pathale WhatsApp number ban korte pare, tai din e alpo kichu,
personalized message pathan.

**Best way:** protiti card e ekta button sobuj thake, oi business ke reach korar sobcheye bhalo upay:

- UAE, Saudi, Qatar, Malaysia, Singapore, UK, Europe (Spain, Italy, Germany, Netherlands...), Africa, BD, India:
  mobile number hole **WhatsApp**.
- US, Canada, Australia, New Zealand, Nordic desh: WhatsApp kom chole, tai **email**, na thakle **Messenger**,
  **contact form** ba **call**.
- Landline number hole WhatsApp jay na, tai email.

### Email edit kore sorasori pathano

Card er **Email** (ba Messages e **Edit & send**, ba report e **Email this report**) chaple email window khule:
To, Subject ar pura lekha nijer moto bodlan. Bodlano lekha oi business ar oi email er jonno mone thake
(**Reset to template** e ager lekha ferot). Tarpor:

- **Send from my Gmail:** tool thekei apnar Gmail diye chole jay, "Send report" e website review ta **PDF
  hoye attach** hoy. Prothombar Google sign-in chay. Ekbar setup lagbe (Settings > Send from Gmail):
  1. Google Cloud e (Maps key er project ei) **Gmail API** chalu korun.
  2. **OAuth consent screen:** External, Testing e rakhun, **Test users** e nijer Gmail din.
  3. **Credentials > Create credentials > OAuth client ID > Web application**. Authorized JavaScript
     origins e `https://raw.githack.com` ar `https://rawcdn.githack.com` din.
  4. Client ID ta Settings e paste korun. (Client ID gopon kichu na, kintu key gulor moto code e rakhini.)
- **Open in Gmail:** setup chara, Gmail e bodlano lekha soho notun email khule.
- Din e 30 tar beshi pathale tool shotorko kore, karon ek Gmail theke beshi cold email gele spam e jay.

Contact button e pathale status nijei agay (First email > Contacted, Follow-up > Followed up, Send report >
Replied, Send sample > Sample sent). Kon message jabe, seta **Messages** e tab diye bodlan.

### Contact nai emon business

**Email khoja:** Google Maps (Places API) kono business er email dey na. Tai **Find email** (card e) ba
**Find emails (N)** (list er upore, shob ek sathe) ei kromey khoje:

1. Oder website er homepage, tarpor contact/about page. Lukano email o dhore: Cloudflare "email protected",
   `info [at] site [dot] com`, `&#64;` ityadi. Oder nijer domain er email (info@, contact@) age rakhe,
   noreply/webmaster baad dey.
2. Facebook page (listing e na thakle web search diye page khuje ber kore, naam mille tobei).
3. Web search er result (naam mile emon result thekei). Eta "from a web search: check it" likha thake,
   pathanor age ekbar mile nin.

Kichu na pele "No email found: search Google" dekhay. Facebook prai login chara page dekhay na, tai Facebook
theke email shob shomoy pawa jay na; tokhon Messenger e message korun.

- Boro search e tool shohor ke kotok bhag e bhag kore khoje (Google proti search e 20 tar beshi dey na),
  tai 60 result er jonno 9 ta search hoy.
- Google e phone na thakle card er **Google Maps** (oder listing, sekhane WhatsApp/website/social link thake)
  ar **Find on Facebook** (onek choto business er shudhu Facebook page thake, Messenger e message korun).
- Google rating (★ 4.6, 120 reviews) card e dekhay. Website nai emon business er first email e ar report e
  eta use hoy ("customers rate you 4.6 stars, but your homepage shows none").
- Ager (OpenStreetMap er) search er lead e phone na thakle **Fill missing contacts from Google** button ase.

Google proti mash e kichu free use dey; tarpor proti search e dam ase. Tai Quotas e limit rakhun.

### Follow-up mone rakha

Protiti card e **Status** ache: Not contacted, Contacted, Followed up, Replied, Sample sent, Client,
Not interested. "Contacted" er 3 din por card upore chole ashe ar "follow-up due" dekhay. **Follow-up due**
filter e shob ek sathe paben. Status ei browser e save thake, CSV te o ase.

Screenshot gulo browser e save hoy na (onek boro). Page bondho kore abar khulle report e
**Re-check for screenshots**, ba prompt e **Take screenshot** chapun.

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

Web version e business er list ase Google Maps theke (nijer Maps API key diye).
Python version e [OpenStreetMap](https://www.openstreetmap.org) theke (free, kono API key lage na).
OpenStreetMap e kom business thake, tai Python e Google Maps theke website gulo copy kore `audit` mode
use korun, ba web version use korun.

## Test

```
python -m unittest test_lead_finder
```

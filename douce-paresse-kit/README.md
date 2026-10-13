# Douce Paresse — Elementor Template Kit

The Douce Paresse website (7 pages + header + footer) as a WordPress plugin for Elementor.

- **One custom widget per section** (category *Douce Paresse* in the Elementor panel). No default Elementor widget is used anywhere.
- **One-click demo import**: *WP Admin → Douce Paresse → Import / Sync now*.
- **Header → Theme Builder › Header** and **Footer → Theme Builder › Footer**, condition *Entire Site* (Elementor Pro).
- **No duplicates**: every imported page, template and image is tagged, so a second import finds it again instead of creating a copy.
- **Smart sync**: kit updates are applied while every text, image, link or style you changed in Elementor is kept.

## Requirements

| | |
|---|---|
| WordPress 6.0+, PHP 7.4+ | |
| Elementor 3.5+ | required |
| Elementor Pro | required for the header/footer in the Theme Builder (without Pro they are saved as section templates; activate Pro and sync again to promote them) |
| Hello Elementor theme | recommended |

## Install

1. *Plugins → Add New → Upload* `douce-paresse-kit.zip`, activate.
2. *Douce Paresse → Demo import & sync*: keep everything ticked, mode **Smart sync**, click **Import / Sync now**.
3. Done: pages are created with the *Elementor Full Width* template, *Accueil* becomes the front page, header and footer are live on the whole site.

## Widgets

| Widget | Used on |
|---|---|
| DP Header | Theme Builder header (desktop menu, logo hover, mobile overlay menu) |
| DP Footer | Theme Builder footer |
| DP Home Hero | Accueil — hero (layout *centered* / *split*) |
| DP Chapter | Accueil — Chapitre I (layout *split* / *full-bleed*) |
| DP Room Showcase & Pop-ups | Accueil — "Pensé pour rester dans la chambre" + 3 cards with pop-ups |
| DP Page Intro | La Maison, Contact, legal pages |
| DP Image & Story | La Maison récit, Coffret I & III, Professionnels expérience |
| DP Hospitality | La Maison — hospitalité |
| DP Centered Statement | La Maison conclusion, Coffret "Une maison dédiée", Professionnels introduction |
| DP Split Hero | Le Coffret Luxury hero, Professionnels hero |
| DP Signature | Le Coffret — "La signature d’un accueil" |
| DP Reasons | Le Coffret — "Pourquoi choisir" |
| DP Services | Professionnels — offre professionnelle |
| DP Professional Request Form | Professionnels — formulaire |
| DP Contact Form & Details | Contact |
| DP Legal Content | Mentions légales, Politique de confidentialité |

Every widget has its content in the *Content* tab and colors / typography / spacing in the *Style* tab.
Forms send an e-mail (recipient set in the widget, default = site admin e-mail) and keep a copy under *Douce Paresse → Messages*.
Fonts (Cormorant Garamond, Jost) are bundled locally — no Google Fonts request.

## Anti-spam (Contact + Professional request forms)

*WP Admin → Douce Paresse → Anti-spam.* Layers, in order:

1. **Honeypot** — invisible field; bots that fill it are silently discarded.
2. **Signed, single-use token + time trap** — fetched by JavaScript (bots posting directly have none), must be at least *N* seconds old (default 4) and can be used once. Unlike a nonce in the HTML it keeps working with page caching.
3. **Cloudflare Turnstile** — free; paste the site key + secret key from *dash.cloudflare.com → Turnstile*. Appearance “only when needed” (invisible for most visitors) or always visible.
4. **Rate limits** — per IP (default 5 / 10 min) and per e-mail address (3 / hour).
5. **Real visitor IP behind Cloudflare** — the orange-cloud proxy *and* Cloudflare Tunnel (cloudflared) are detected; `CF-Connecting-IP` is only trusted when the request really comes from a Cloudflare range or the local tunnel, so it cannot be spoofed from outside.
6. **Content rules** — more than 2 links, `[url]` BBCode / HTML links, blocked words (editable list, whole words), WordPress *Disallowed Comment Keys*, mostly non-Latin text (Cyrillic/Chinese spam on a French site), length limits.
7. **E-mail rules** — disposable addresses refused, domains without MX/A record refused (shown to the visitor so typos can be fixed).
8. **Akismet** — used automatically when the Akismet plugin is active and connected.
9. **Duplicates** — the same message sent twice within an hour is not e-mailed twice.

Bots get a fake “sent” (they learn nothing); blocked messages appear under *Messages → Spam* with the reason and are deleted after 30 days. Humans only ever see a message when they can fix something (wait, solve the challenge, correct the e-mail).

> Privacy: Turnstile (Cloudflare) and Akismet process visitor data outside the site. If you enable them, mention them in *Politique de confidentialité* (section 5 “Destinataires et prestataires” and section 8 “Cookies et autres traceurs”).

## Import modes (when an item already exists)

- **Smart sync (recommended)** — three-way merge between *what the kit imported last time*, *what is on the site now* and *the new kit version*:
  - a value you changed (text, image, link, color, typography, spacing, custom CSS…) is **always kept**;
  - a value you did not touch follows the new kit version;
  - sections you added stay where they are, sections you deleted stay deleted, new kit sections are inserted next to their neighbours.
- **Only import what is missing** — existing items are not touched.
- **Reset to the kit version** — overwrites; the previous version is kept and can be brought back with *Restore backup*.

Every sync/reset that changes a page first saves a backup (*Restore backup* link in the table). Pages in the trash are never re-created.

### Typical workflow

1. Import the kit, then edit pages freely in Elementor.
2. When the kit is updated (new plugin version, `demo/demo-data.php` changed), upload the new plugin and run **Smart sync**: the table shows *Kit update available* and *N edits of yours (protected)*; your edits stay, the kit changes arrive.
3. Changes to a widget's design (PHP/CSS) apply automatically after updating the plugin — no re-import needed, your content is untouched.

## Diagnostics

*WP Admin → Douce Paresse → Diagnostics* checks the server side: versions, duplicate plugin copies, whether the kit files on disk are current, cache / optimisation plugins and their “delay / combine JavaScript” settings, Elementor settings, every Theme Builder header/footer and its conditions, the DP Header “Fixed on scroll” setting and animation / motion / z-index settings on its containers, kit pages and images, e-mail failures, kit-related lines of `debug.log`, and the home page as a visitor receives it (cached copy, old CSS/JS version, delayed script, which header template is really shown).

**Browser check**: open any page with `?dpk-debug=1` (or click *DP Debug* in the admin bar; administrators only, never cached). A panel checks the live page in the browser: kit script/CSS loaded and their version, header lifted under `<body>`, the CSS rules that set its position (selector + file), wrappers and their CSS that break `position:fixed`, a real scroll test, what covers the header, other headers, fonts, broken images, horizontal overflow, underlined/active menu links, duplicate ids, JavaScript errors, the form token endpoint and Turnstile. **Copy report** puts everything (server summary included) on the clipboard.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Header not fixed, unstyled for a moment | Fixed in 1.1.2: the kit CSS is now loaded in `<head>` on every page. Clear any cache after updating. |
| Every menu link underlined | Happened with *Settings → Permalinks → Plain* (all pages are `/?page_id=N`). Fixed in 1.1.2; links saved as `?page_id=` are shown as the current permalink since 1.1.3. “Post name” permalinks are recommended. |
| Header never fixed although “Fixed on scroll” is on | Before 1.1.6 the switch was named `sticky`, the same id as the Elementor Pro *Motion Effects → Sticky* control that Pro adds to every widget; Pro’s control (default: none) replaced it. Renamed to `dpk_fixed` (`tests/controls-test.php` guards against reserved ids). |
| Header scrolls away or slides under the content | An entrance animation, motion effect, z-index or theme CSS on the Theme Builder wrappers breaks `position:fixed`. Since 1.1.4 the header widget is moved into its own wrapper directly under `<body>` on the live site (like the original); since 1.1.5 the same protection also works in CSS alone (script delayed by an optimisation plugin / Rocket Loader). Run *Diagnostics* + the browser check if it still happens. |
| No header/footer at all | A block theme (Twenty Twenty-Four/Five…) is active — Theme Builder needs a classic theme such as Hello Elementor. Also check that Elementor Pro is active. |

## Developers

```
demo/demo-data.php     demo content (tokens {{img:file}} and {{page:key}})
widgets/               one class per section widget
includes/class-sync.php      three-way merge (pure PHP)
includes/class-importer.php  import / sync / backup
tests/                 php tests/sync-test.php && php tests/importer-test.php && php tests/spam-test.php && php tests/links-test.php && php tests/debug-test.php && php tests/controls-test.php
includes/class-spam.php      anti-spam layers + settings screen
includes/class-debug.php     Diagnostics screen + ?dpk-debug=1 browser panel (assets/js/debug.js)
build.sh               creates douce-paresse-kit.zip (without tests)
```

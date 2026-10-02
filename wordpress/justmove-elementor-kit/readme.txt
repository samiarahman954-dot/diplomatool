=== Just Move DFW – Elementor Template Kit ===
Requires at least: 6.0
Requires PHP: 7.4
Requires Plugins: elementor
Stable tag: 1.3.0

The Just Move DFW home page rebuilt as 18 custom Elementor widgets (one per section) with one-click demo import. No default Elementor widgets are used.

== Installation ==

1. Install and activate Elementor (free is enough, 3.5+).
2. Plugins → Add New → Upload Plugin → choose justmove-elementor-kit.zip → Activate.
3. Go to "Just Move Kit" in the admin menu and click "Import demo now".
   - Creates a page "Home – Just Move DFW" on the Elementor Canvas template.
   - Optionally sets it as the front page and saves a copy to Elementor → Templates.
   - With Elementor Pro active: the header is imported as a Theme Builder "Header" template and the
     footer (+ mobile call bar) as a "Footer" template, both set to "Entire Site". The page then holds
     only the body sections and uses the "Elementor Full Width" template so they show.
     Find them under Templates → Theme Builder. Re-importing keeps the existing header/footer.
   - Without Elementor Pro: header and footer stay inside the page (Elementor Canvas template).
4. Edit with Elementor: every section is its own widget in the "Just Move DFW" panel category.

== Elementor Global styles ==

Tick "Add the kit's colours & fonts to Elementor Global styles" on import, or use the
"Elementor Global styles" card on the Just Move Kit screen later.

* Adds 12 colours (JM Yellow, JM Blue, JM Background, JM Quote Navy, …) and 4 fonts (JM Display =
  Anton, JM Body = Inter, JM Quote Heading = Archivo, JM Quote Body = Hanken Grotesk) to
  Elementor → Site Settings → Global Colors / Global Fonts.
* The kit follows them: change "JM Yellow" in Site Settings and every kit section updates. They can
  also be picked in any other Elementor widget. Without them the kit uses its built-in values.
* Running it again never duplicates; colours/fonts you already edited are kept.
* Optional (off by default): also set Elementor's default Primary/Secondary/Text/Accent colours and
  fonts. These affect every Elementor widget left on "Default", so other pages may change too.
* A backup of Site Settings is taken first and can be restored from the Backups card.

== Updating without losing your edits ==

* Import runs once. Clicking it again never creates a second copy.
* Plugin updates (design, fixes, new options) apply as soon as the plugin is updated — no
  re-import. Upload the new zip and choose "Replace current with uploaded".
* Then, if the update notes mention new sections, click Just Move Kit → "Sync now". Sync never
  changes existing sections, their text/images/links/colours/style settings, their order, or
  anything you added; sections or pages you deleted are not brought back. It only adds kit
  sections the page has never had.
* "Reset to demo layout" is separate, asks for confirmation, and is always backed up first.
* Backups (last 5 per page/template) are taken before every sync, reset or restore and can be
  restored with one click from the Just Move Kit screen.

== Widgets ==

JM Header / Nav · JM Hero + Quote Form · JM Trust Strip · JM Intro Text · JM Route Divider ·
JM Services Grid · JM Same-Day Banner · JM Why Choose Us · JM How It Works (Steps) ·
JM Specialty Moves · JM Reviews · JM Estimate Band · JM Service Areas · JM Moving Guide (Article) ·
JM FAQ (with FAQPage schema) · JM Final CTA · JM Footer (with MovingCompany schema) · JM Mobile Call Bar

Every widget has Content controls for all text/links/lists and a Style tab with brand colours,
section padding, max width and typography.

== Quote builder (instant estimates) ==

Add it with the "JM Quote Builder" Elementor widget or the shortcode [jmk_quote_builder]
(optional attributes: brand="", tagline="", logo="URL", background="no").
The demo import also creates a "Get a Quote" page at /quote/ (unless one exists).

* 6 steps: move type, size, addresses + access, date, services/photos, contact.
* Local and labor-only moves get an instant price range; long-distance and commercial get a
  "custom quote" message.
* The price is calculated on the server from Just Move Kit → Quote Builder (hourly rates,
  travel fee, hours per size, crew, packing, materials, specialty items, stairs), so what the
  customer sees, what is saved and what is emailed always match.
* Each request is saved under Just Move Kit → Leads with its estimate number and photos, emailed
  to you (Reply-To = customer) and, if the customer gave an email, emailed to the customer too.
* Customers can download a branded PDF estimate (jsPDF is bundled and loads only on click).
* Photos: up to 10 files (images/PDF), large phone photos are shrunk in the browser before
  upload, stored with random names in uploads/jmk-quotes/, and deleted with the lead.
* Spam protection: honeypot + max 20 requests per 10 minutes per IP; customer copies are
  capped at 3 per email address per hour.
* Developers: `jmk_quote_received` ( $record, $post_id ) fires after each request.

== Quote form ==

Hero form submissions are saved under Just Move Kit → Leads and emailed to the address on the
Just Move Kit screen (defaults to the site admin email). Spam protection: honeypot + rate limit.
Developers can hook `jmk_lead_received` ( $lead, $post_id ) to push leads to a CRM or SMS service.

== Notes ==

* Links default to WordPress-style paths (/quote/, /local-moving/, /movers-dallas/ …). Create those
  pages or change the links in each widget.
* The page <title> and meta description belong to your SEO plugin (Yoast, Rank Math, etc.).

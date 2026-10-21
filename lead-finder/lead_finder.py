#!/usr/bin/env python3
"""
Lead Finder - find local businesses with weak websites and draft outreach emails.

Two modes:
  search  Find businesses of a given type in a city (OpenStreetMap data),
          audit their websites and rank them as leads.
  audit   Audit a list of websites you collected yourself (e.g. from Google Maps).

Only the Python standard library is used, so no `pip install` is needed.

Examples:
  python lead_finder.py search --city "Austin, Texas" --category dentist
  python lead_finder.py search --city "Manchester, UK" --category plumber --limit 80
  python lead_finder.py audit --input my_sites.txt
  python lead_finder.py categories
"""

import argparse
import csv
import datetime
import html
import json
import os
import re
import ssl
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass, field
from typing import Dict, List, Optional, Tuple

BROWSER_UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/124.0 Safari/537.36"
)
NOMINATIM_URL = "https://nominatim.openstreetmap.org/search"
OVERPASS_URLS = [
    "https://overpass-api.de/api/interpreter",
    "https://overpass.kumi.systems/api/interpreter",
    "https://overpass.private.coffee/api/interpreter",
]
PAGESPEED_URL = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed"

CURRENT_YEAR = datetime.date.today().year
# Kept conservative on purpose: an email that wrongly calls a site "outdated" kills trust.
WP_OUTDATED_BELOW = (6, 0)
SLOW_SECONDS = 3.0
MAX_BODY_BYTES = 2_000_000

# Business type -> OpenStreetMap tags. Use --tag key=value for anything not listed.
CATEGORIES: Dict[str, List[Tuple[str, str]]] = {
    "dentist": [("amenity", "dentist"), ("healthcare", "dentist")],
    "doctor": [("amenity", "doctors"), ("amenity", "clinic")],
    "veterinary": [("amenity", "veterinary")],
    "pharmacy": [("amenity", "pharmacy")],
    "lawyer": [("office", "lawyer")],
    "accountant": [("office", "accountant"), ("office", "tax_advisor")],
    "insurance": [("office", "insurance")],
    "real_estate": [("office", "estate_agent"), ("shop", "estate_agent")],
    "architect": [("office", "architect")],
    "plumber": [("craft", "plumber")],
    "electrician": [("craft", "electrician")],
    "hvac": [("craft", "hvac")],
    "roofer": [("craft", "roofer")],
    "carpenter": [("craft", "carpenter")],
    "painter": [("craft", "painter")],
    "car_repair": [("shop", "car_repair")],
    "car_dealer": [("shop", "car")],
    "restaurant": [("amenity", "restaurant")],
    "cafe": [("amenity", "cafe")],
    "bakery": [("shop", "bakery")],
    "hotel": [("tourism", "hotel"), ("tourism", "guest_house")],
    "salon": [("shop", "hairdresser"), ("shop", "beauty")],
    "spa": [("leisure", "sauna"), ("shop", "massage"), ("amenity", "spa")],
    "gym": [("leisure", "fitness_centre")],
    "florist": [("shop", "florist")],
    "furniture": [("shop", "furniture")],
    "clothes": [("shop", "clothes"), ("shop", "boutique")],
    "jewelry": [("shop", "jewelry")],
    "optician": [("shop", "optician")],
    "driving_school": [("amenity", "driving_school")],
    "school": [("amenity", "school"), ("amenity", "college")],
    "kindergarten": [("amenity", "kindergarten")],
    "travel_agency": [("shop", "travel_agency"), ("office", "travel_agent")],
    "photographer": [("craft", "photographer"), ("shop", "photo")],
}

ANALYTICS_MARKERS = (
    "googletagmanager.com", "google-analytics.com", "gtag(", "fbq(", "plausible.io",
    "matomo", "clarity.ms", "hotjar", "segment.com", "umami",
)
PLATFORM_MARKERS = [
    ("WordPress", ("wp-content", "wp-includes")),
    ("Wix", ("wixstatic.com", "parastorage.com", "wix.com")),
    ("Squarespace", ("squarespace",)),
    ("Shopify", ("cdn.shopify.com", "myshopify.com")),
    ("Webflow", ("webflow",)),
    ("GoDaddy Builder", ("wsimg.com",)),
    ("Weebly", ("weebly",)),
    ("Duda", ("multiscreensite", "dudaone")),
    ("Joomla", ("/media/jui/", "joomla")),
    ("Drupal", ("drupal",)),
]
EMAIL_RE = re.compile(r"[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}")
EMAIL_JUNK = (
    ".png", ".jpg", ".jpeg", ".gif", ".webp", ".svg", "example.com", "domain.com",
    "sentry", "wixpress", "yourname", "email.com", "yourdomain", "@2x",
)
COPYRIGHT_RE = re.compile(
    r"(?:©|&copy;|&#169;|copyright)\s*(?:&nbsp;|\s)*((?:19|20)\d{2})"
    r"(?:\s*(?:-|–|&ndash;|to)\s*((?:19|20)\d{2}))?",
    re.IGNORECASE,
)


# ---------------------------------------------------------------------------
# Data
# ---------------------------------------------------------------------------

@dataclass
class Business:
    name: str
    website: str = ""
    phone: str = ""
    email: str = ""
    address: str = ""
    facebook: str = ""
    source_url: str = ""


@dataclass
class Audit:
    url: str
    final_url: str = ""
    status: int = 0
    error: str = ""
    elapsed: float = 0.0
    platform: str = ""
    wp_version: str = ""
    copyright_year: int = 0
    pagespeed: Optional[int] = None
    emails: List[str] = field(default_factory=list)
    issues: List[str] = field(default_factory=list)


@dataclass
class Lead:
    business: Business
    audit: Optional[Audit]
    score: int = 0
    findings: List[str] = field(default_factory=list)
    subject: str = ""
    body: str = ""


# ---------------------------------------------------------------------------
# HTTP helpers
# ---------------------------------------------------------------------------

def http_get(url: str, timeout: float = 15, headers: Optional[Dict[str, str]] = None,
             verify: bool = True) -> Tuple[int, str, bytes, float]:
    """Return (status, final_url, body, elapsed_seconds). HTTP errors still return a body."""
    req = urllib.request.Request(url, headers={"User-Agent": BROWSER_UA, **(headers or {})})
    ctx = None if verify else ssl._create_unverified_context()
    start = time.monotonic()
    try:
        with urllib.request.urlopen(req, timeout=timeout, context=ctx) as resp:
            body = resp.read(MAX_BODY_BYTES)
            return resp.status, resp.geturl(), body, time.monotonic() - start
    except urllib.error.HTTPError as e:
        body = e.read(MAX_BODY_BYTES) if e.fp else b""
        return e.code, e.geturl() or url, body, time.monotonic() - start


def http_post_form(url: str, data: Dict[str, str], timeout: float, user_agent: str) -> bytes:
    payload = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(url, data=payload, headers={"User-Agent": user_agent})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return resp.read()


def decode(body: bytes) -> str:
    return body.decode("utf-8", errors="ignore")


def normalize_url(url: str) -> str:
    url = url.strip()
    if not url:
        return ""
    if not re.match(r"^https?://", url, re.IGNORECASE):
        url = "http://" + url
    return url


def domain_of(url: str) -> str:
    host = urllib.parse.urlparse(normalize_url(url)).netloc.lower()
    return host[4:] if host.startswith("www.") else host


# ---------------------------------------------------------------------------
# Finding businesses (OpenStreetMap)
# ---------------------------------------------------------------------------

def geocode(city: str, user_agent: str) -> dict:
    query = urllib.parse.urlencode({"q": city, "format": "jsonv2", "limit": 1})
    req = urllib.request.Request(f"{NOMINATIM_URL}?{query}", headers={"User-Agent": user_agent})
    with urllib.request.urlopen(req, timeout=30) as resp:
        results = json.loads(resp.read())
    if not results:
        raise SystemExit(f"City not found: {city!r}. Try adding the country, e.g. \"Austin, Texas, USA\".")
    return results[0]


def build_overpass_query(place: dict, tags: List[Tuple[str, str]], limit: int) -> str:
    if place.get("osm_type") == "relation":
        area = f"area(id:{3600000000 + int(place['osm_id'])})->.a;"
        scope = "(area.a)"
    else:
        south, north, west, east = place["boundingbox"]
        area = ""
        scope = f"({south},{west},{north},{east})"
    selectors = "\n".join(f'  nwr["{k}"="{v}"]["name"]{scope};' for k, v in tags)
    return f"[out:json][timeout:120];\n{area}\n(\n{selectors}\n);\nout tags center {limit};"


def run_overpass(query: str, user_agent: str) -> List[dict]:
    last_error = None
    for endpoint in OVERPASS_URLS:
        try:
            return json.loads(http_post_form(endpoint, {"data": query}, 180, user_agent))["elements"]
        except Exception as e:  # try the next mirror
            last_error = e
            log(f"  Overpass mirror failed ({endpoint}): {e}")
    raise SystemExit(f"All Overpass servers failed: {last_error}")


def element_to_business(el: dict) -> Optional[Business]:
    t = el.get("tags", {})
    name = t.get("name")
    if not name:
        return None
    address = ", ".join(
        p for p in (
            " ".join(x for x in (t.get("addr:housenumber"), t.get("addr:street")) if x),
            t.get("addr:city", ""),
            t.get("addr:postcode", ""),
        ) if p
    )
    return Business(
        name=name,
        website=t.get("website") or t.get("contact:website") or t.get("url") or "",
        phone=t.get("phone") or t.get("contact:phone") or t.get("contact:mobile") or "",
        email=t.get("email") or t.get("contact:email") or "",
        address=address,
        facebook=t.get("contact:facebook") or t.get("facebook") or "",
        source_url=f"https://www.openstreetmap.org/{el['type']}/{el['id']}",
    )


def is_chain(el: dict) -> bool:
    t = el.get("tags", {})
    return bool(t.get("brand") or t.get("brand:wikidata"))


# ---------------------------------------------------------------------------
# Website audit
# ---------------------------------------------------------------------------

def detect_platform(text: str) -> Tuple[str, str]:
    """Return (platform, wordpress_version)."""
    low = text.lower()
    gen = re.search(r'<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']+)', text, re.I) \
        or re.search(r'<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']generator["\']', text, re.I)
    generator = gen.group(1) if gen else ""
    wp = re.search(r"wordpress\s+(\d+(?:\.\d+)*)", generator, re.I)
    for name, markers in PLATFORM_MARKERS:
        if any(m in low for m in markers) or name.lower() in generator.lower():
            return name, (wp.group(1) if wp and name == "WordPress" else "")
    return "", ""


def latest_copyright_year(text: str) -> int:
    years = []
    for m in COPYRIGHT_RE.finditer(text):
        years += [int(y) for y in m.groups() if y]
    years = [y for y in years if y <= CURRENT_YEAR]
    return max(years) if years else 0


def find_emails(text: str) -> List[str]:
    found = []
    for e in EMAIL_RE.findall(html.unescape(text)):
        e = e.strip(".").lower()
        if not any(j in e for j in EMAIL_JUNK) and e not in found:
            found.append(e)
    return found


def find_contact_page(text: str, base_url: str) -> str:
    for href in re.findall(r'href=["\']([^"\'#]+)["\']', text, re.I):
        if re.search(r"contact|kontakt|about", href, re.I) and not href.lower().startswith("mailto:"):
            url = urllib.parse.urljoin(base_url, href)
            if domain_of(url) == domain_of(base_url):
                return url
    return ""


def version_tuple(v: str) -> Tuple[int, ...]:
    return tuple(int(x) for x in v.split(".") if x.isdigit())


def pagespeed_score(url: str, api_key: str) -> Optional[int]:
    query = urllib.parse.urlencode(
        {"url": url, "strategy": "mobile", "category": "performance", "key": api_key})
    try:
        _, _, body, _ = http_get(f"{PAGESPEED_URL}?{query}", timeout=90)
        score = json.loads(body)["lighthouseResult"]["categories"]["performance"]["score"]
        return round(score * 100)
    except Exception:
        return None


def audit_website(url: str, psi_key: str = "") -> Audit:
    url = normalize_url(url)
    a = Audit(url=url)
    try:
        try:
            status, final, body, elapsed = http_get(url)
        except urllib.error.URLError as e:
            if not isinstance(getattr(e, "reason", None), ssl.SSLError):
                raise
            a.issues.append("ssl_broken")
            status, final, body, elapsed = http_get(url, verify=False)
    except Exception as e:
        a.error = str(getattr(e, "reason", e))[:150]
        a.issues.append("down")
        return a

    a.status, a.final_url, a.elapsed = status, final, elapsed
    if status >= 400:
        a.error = f"HTTP {status}"
        a.issues.append("down")
        return a

    text = decode(body)
    low = text.lower()
    a.platform, a.wp_version = detect_platform(text)
    a.copyright_year = latest_copyright_year(text)
    a.emails = find_emails(text)

    if not final.lower().startswith("https://"):
        a.issues.append("no_https")
    if not re.search(r'<meta[^>]+name=["\']viewport["\']', text, re.I):
        a.issues.append("no_viewport")
    if elapsed > SLOW_SECONDS:
        a.issues.append("slow")
    if a.copyright_year and a.copyright_year <= CURRENT_YEAR - 2:
        a.issues.append("outdated_copyright")
    if a.wp_version and version_tuple(a.wp_version) < WP_OUTDATED_BELOW:
        a.issues.append("old_wp")
    if re.search(r"jquery[.-]?1\.\d+(\.\d+)?(\.min)?\.js|jquery[^\"']*\?ver=1\.\d", low):
        a.issues.append("old_jquery")
    title = re.search(r"<title[^>]*>(.*?)</title>", text, re.I | re.S)
    if not title or len(title.group(1).strip()) < 4:
        a.issues.append("no_title")
    if not re.search(r'<meta[^>]+name=["\']description["\']', text, re.I):
        a.issues.append("no_meta_desc")
    if not any(m in low for m in ANALYTICS_MARKERS):
        a.issues.append("no_analytics")

    if not a.emails:
        contact = find_contact_page(text, final)
        if contact:
            try:
                _, _, cbody, _ = http_get(contact, verify="ssl_broken" not in a.issues)
                a.emails = find_emails(decode(cbody))
            except Exception:
                pass

    if psi_key:
        a.pagespeed = pagespeed_score(final, psi_key)
        if a.pagespeed is not None and a.pagespeed < 50:
            a.issues.append("psi_low")
    return a


# ---------------------------------------------------------------------------
# Scoring and email drafts
# ---------------------------------------------------------------------------

def issue_points_and_text(code: str, a: Audit) -> Tuple[int, str]:
    table = {
        "down": (80, "your website didn't load when I tried to open it"
                 + (f" ({a.error})" if a.error else "") + ", so customers who search for you hit a dead end"),
        "ssl_broken": (35, "browsers show a security warning on your site because its SSL certificate is broken"),
        "no_https": (30, "the site isn't on HTTPS, so Chrome labels it \"Not Secure\" next to your address"),
        "no_viewport": (30, "the site isn't mobile-friendly, so it's hard to read and use on a phone"),
        "psi_low": (25, f"Google PageSpeed scores the mobile version {a.pagespeed}/100, which hurts your Google ranking"),
        "slow": (20, f"the homepage took {a.elapsed:.1f} seconds to load for me; most visitors leave after 3"),
        "old_wp": (20, f"it runs an old WordPress version ({a.wp_version}), which is a common way sites get hacked"),
        "outdated_copyright": (15, f"the footer still says © {a.copyright_year}, which can make the business look inactive"),
        "old_jquery": (10, "it loads outdated JavaScript libraries that have known security issues"),
        "no_title": (10, "the homepage has no proper page title, which hurts how you show up on Google"),
        "no_meta_desc": (8, "the homepage has no meta description, so Google picks random text for your search listing"),
        "no_analytics": (5, "there's no visitor tracking set up, so you can't see how many people visit or where they come from"),
    }
    return table[code]


def score_lead(b: Business, a: Optional[Audit]) -> Tuple[int, List[str]]:
    if a is None:
        # Has a phone or address but no website at all.
        return 70, ["no website"]
    scored = sorted((issue_points_and_text(c, a) for c in a.issues), key=lambda x: -x[0])
    return min(100, sum(p for p, _ in scored)), [t for _, t in scored]


def draft_email(b: Business, a: Optional[Audit], findings: List[str], ctx: argparse.Namespace) -> Tuple[str, str]:
    where = f" in {ctx.city_label}" if getattr(ctx, "city_label", "") else ""
    what = getattr(ctx, "category_label", "") or "local businesses"
    signature = "\n".join(x for x in (ctx.your_name, ctx.your_site) if x)

    if a is None:
        subject = f"A website for {b.name}?"
        body = (
            f"Hi {b.name} team,\n\n"
            f"I came across {b.name} while looking at {what}{where}, and noticed you don't seem to have a website.\n\n"
            "Most people search Google before they call or visit, and without a site they usually end up "
            "with a competitor who has one.\n\n"
            "I build fast, mobile-friendly websites for small businesses, with your services, opening hours, "
            "reviews and a simple contact or booking form. I'd be happy to put together a free homepage mock-up "
            "so you can see what it would look like, with no obligation.\n\n"
            "Would that be useful?\n\n"
            f"Best regards,\n{signature}"
        )
        return subject, body

    subject = f"Quick note about {b.name}'s website"
    bullets = "\n".join(f"- {f[0].upper()}{f[1:]}" for f in findings[:3])
    body = (
        f"Hi {b.name} team,\n\n"
        f"I came across {b.name} while looking at {what}{where} and had a quick look at your website "
        f"({domain_of(a.url)}). A few things stood out that are probably costing you customers:\n\n"
        f"{bullets}\n\n"
        "All of these are fixable, usually within a few days. I'm a web developer who works with "
        "WordPress and small business websites, and I'd be happy to record a short video showing "
        "exactly how I'd fix them, free of charge.\n\n"
        "Would that be helpful?\n\n"
        f"Best regards,\n{signature}"
    )
    return subject, body


def build_leads(businesses: List[Business], ctx: argparse.Namespace) -> List[Lead]:
    with_site = [b for b in businesses if b.website]
    without_site = [] if ctx.skip_no_website else [b for b in businesses if not b.website and (b.phone or b.address)]

    log(f"Checking {len(with_site)} websites ({ctx.workers} at a time)...")
    audits: Dict[int, Audit] = {}
    with ThreadPoolExecutor(max_workers=ctx.workers) as pool:
        futures = {pool.submit(audit_website, b.website, ctx.psi_key): i for i, b in enumerate(with_site)}
        for n, fut in enumerate(futures, 1):
            i = futures[fut]
            audits[i] = fut.result()
            log(f"  [{n}/{len(with_site)}] {with_site[i].name}: {', '.join(audits[i].issues) or 'looks fine'}")

    leads = []
    for i, b in enumerate(with_site):
        a = audits[i]
        if not b.email and a.emails:
            b.email = a.emails[0]
        leads.append(Lead(b, a))
    leads += [Lead(b, None) for b in without_site]

    for lead in leads:
        lead.score, lead.findings = score_lead(lead.business, lead.audit)
        lead.subject, lead.body = draft_email(lead.business, lead.audit, lead.findings, ctx)
    leads = [l for l in leads if l.score >= ctx.min_score]
    leads.sort(key=lambda l: (-l.score, l.business.name.lower()))
    return leads


# ---------------------------------------------------------------------------
# Output
# ---------------------------------------------------------------------------

CSV_FIELDS = ["score", "name", "website", "email", "phone", "address", "facebook", "platform",
              "problems", "email_subject", "email_body", "source"]


def write_csv(leads: List[Lead], path: str) -> None:
    # utf-8-sig so Excel opens non-English names correctly.
    with open(path, "w", newline="", encoding="utf-8-sig") as f:
        w = csv.writer(f)
        w.writerow(CSV_FIELDS)
        for l in leads:
            b, a = l.business, l.audit
            w.writerow([l.score, b.name, b.website, b.email, b.phone, b.address, b.facebook,
                        (a.platform if a else ""), " | ".join(l.findings), l.subject, l.body, b.source_url])


def write_html(leads: List[Lead], path: str, title: str) -> None:
    def esc(s) -> str:
        return html.escape(str(s or ""))

    cards = []
    for n, l in enumerate(leads):
        b, a = l.business, l.audit
        level = "hot" if l.score >= 60 else "warm" if l.score >= 30 else "cold"
        mailto = ""
        if b.email:
            mailto = "mailto:" + b.email + "?" + urllib.parse.urlencode(
                {"subject": l.subject, "body": l.body}, quote_via=urllib.parse.quote)
        contact = []
        if b.website:
            contact.append(f'<a href="{esc(normalize_url(b.website))}" target="_blank" rel="noopener">{esc(domain_of(b.website))}</a>')
        if b.email:
            contact.append(f"<span>{esc(b.email)}</span>")
        if b.phone:
            contact.append(f"<span>{esc(b.phone)}</span>")
        if b.facebook:
            contact.append(f'<a href="{esc(b.facebook)}" target="_blank" rel="noopener">Facebook</a>')
        meta = " · ".join(x for x in (esc(b.address), esc(a.platform) if a else "") if x)
        problems = "".join(f"<li>{esc(f[0].upper() + f[1:])}</li>" for f in l.findings)
        cards.append(f"""
<article class="card {level}">
  <header>
    <div><h2>{esc(b.name)}</h2><p class="meta">{meta}</p></div>
    <span class="score" title="Opportunity score">{l.score}</span>
  </header>
  <p class="contact">{" · ".join(contact)}</p>
  <ul>{problems}</ul>
  <details>
    <summary>Email draft</summary>
    <p class="subject"><b>Subject:</b> <span id="s{n}">{esc(l.subject)}</span></p>
    <pre id="b{n}">{esc(l.body)}</pre>
    <div class="actions">
      <button onclick="copyDraft({n}, this)">Copy email</button>
      {f'<a class="btn" href="{esc(mailto)}">Open in mail app</a>' if mailto else '<span class="hint">No email found: call, use the contact form, or find the owner on LinkedIn</span>'}
      {f'<a class="btn ghost" href="{esc(b.source_url)}" target="_blank" rel="noopener">Map</a>' if b.source_url else ''}
    </div>
  </details>
</article>""")

    hot = sum(1 for l in leads if l.score >= 60)
    with_email = sum(1 for l in leads if l.business.email)
    page = f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{esc(title)}</title>
<style>
:root {{ --bg:#f6f7f9; --card:#fff; --text:#1c1f24; --muted:#5f6670; --line:#e3e6ea;
  --hot:#c62828; --warm:#d97706; --cold:#64748b; --accent:#2563eb; }}
@media (prefers-color-scheme: dark) {{
  :root {{ --bg:#111418; --card:#1a1e24; --text:#e8eaed; --muted:#9aa3ad; --line:#2a3038;
    --hot:#ef5350; --warm:#f59e0b; --cold:#94a3b8; --accent:#60a5fa; }} }}
* {{ box-sizing:border-box; }}
body {{ margin:0; background:var(--bg); color:var(--text); font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }}
main {{ max-width:860px; margin:0 auto; padding:24px 16px 64px; }}
h1 {{ font-size:22px; margin:0 0 4px; }}
.summary {{ color:var(--muted); margin:0 0 20px; }}
.filters {{ display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }}
.filters button {{ border:1px solid var(--line); background:var(--card); color:var(--text); padding:6px 12px; border-radius:999px; cursor:pointer; }}
.filters button.on {{ border-color:var(--accent); color:var(--accent); }}
.card {{ background:var(--card); border:1px solid var(--line); border-left:4px solid var(--cold); border-radius:10px; padding:14px 16px; margin-bottom:12px; }}
.card.hot {{ border-left-color:var(--hot); }} .card.warm {{ border-left-color:var(--warm); }}
header {{ display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }}
h2 {{ font-size:17px; margin:0; }}
.meta, .contact, .hint {{ color:var(--muted); font-size:13px; margin:2px 0; overflow-wrap:anywhere; }}
.contact {{ margin-top:6px; }}
a {{ color:var(--accent); }}
.score {{ min-width:42px; text-align:center; font-weight:700; padding:4px 8px; border-radius:8px; color:#fff; background:var(--cold); }}
.hot .score {{ background:var(--hot); }} .warm .score {{ background:var(--warm); }}
ul {{ margin:8px 0; padding-left:20px; }}
summary {{ cursor:pointer; color:var(--accent); font-weight:600; }}
pre {{ white-space:pre-wrap; background:var(--bg); border:1px solid var(--line); border-radius:8px; padding:12px; font:13px/1.5 ui-monospace,Consolas,monospace; }}
.actions {{ display:flex; gap:8px; flex-wrap:wrap; align-items:center; }}
button, .btn {{ border:0; background:var(--accent); color:#fff; padding:7px 12px; border-radius:8px; font:inherit; font-size:13px; cursor:pointer; text-decoration:none; }}
.btn.ghost {{ background:transparent; color:var(--accent); border:1px solid var(--line); }}
</style></head>
<body><main>
<h1>{esc(title)}</h1>
<p class="summary">{len(leads)} leads · {hot} hot · {with_email} with email · generated {datetime.date.today().isoformat()}</p>
<div class="filters">
  <button class="on" onclick="filt('all', this)">All</button>
  <button onclick="filt('hot', this)">Hot (60+)</button>
  <button onclick="filt('warm', this)">Warm (30-59)</button>
  <button onclick="filt('email', this)">Has email</button>
</div>
{''.join(cards) or '<p>No leads found.</p>'}
</main>
<script>
function copyDraft(n, btn) {{
  const text = 'Subject: ' + document.getElementById('s' + n).innerText + '\\n\\n' + document.getElementById('b' + n).innerText;
  navigator.clipboard.writeText(text).then(() => {{ btn.textContent = 'Copied'; setTimeout(() => btn.textContent = 'Copy email', 1500); }});
}}
function filt(kind, btn) {{
  document.querySelectorAll('.filters button').forEach(b => b.classList.toggle('on', b === btn));
  document.querySelectorAll('.card').forEach(c => {{
    const show = kind === 'all' || c.classList.contains(kind) || (kind === 'email' && c.querySelector('a[href^="mailto:"]'));
    c.style.display = show ? '' : 'none';
  }});
}}
</script>
</body></html>"""
    with open(path, "w", encoding="utf-8") as f:
        f.write(page)


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def log(msg: str) -> None:
    print(msg, file=sys.stderr, flush=True)


def slug(s: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", s.lower()).strip("-")[:40] or "leads"


def parse_tags(values: List[str]) -> List[Tuple[str, str]]:
    tags = []
    for v in values:
        if "=" not in v:
            raise SystemExit(f"--tag must look like key=value, got {v!r}")
        k, val = v.split("=", 1)
        tags.append((k.strip(), val.strip()))
    return tags


def read_input_file(path: str) -> List[Business]:
    """Accept a CSV with name/website columns, or a text file with one site per line
    (optionally `Business Name | https://site.com`)."""
    businesses = []
    with open(path, encoding="utf-8-sig") as f:
        if path.lower().endswith(".csv"):
            for row in csv.DictReader(f):
                row = {k.strip().lower(): (v or "").strip() for k, v in row.items() if k}
                site = row.get("website") or row.get("url") or row.get("site") or ""
                if site:
                    businesses.append(Business(
                        name=row.get("name") or row.get("business") or domain_of(site),
                        website=site, phone=row.get("phone", ""), email=row.get("email", ""),
                        address=row.get("address", "")))
        else:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#"):
                    continue
                name, _, site = line.rpartition("|")
                site = site.strip()
                businesses.append(Business(name=name.strip() or domain_of(site), website=site))
    return businesses


def finish(leads: List[Lead], out_base: str, title: str) -> None:
    os.makedirs(os.path.dirname(out_base) or ".", exist_ok=True)
    write_csv(leads, out_base + ".csv")
    write_html(leads, out_base + ".html", title)
    log("")
    log(f"Done. {len(leads)} leads saved:")
    log(f"  {out_base}.html  <- open this in your browser")
    log(f"  {out_base}.csv   <- open in Excel / Google Sheets")
    if leads:
        log("\nTop leads:")
        for l in leads[:10]:
            log(f"  {l.score:>3}  {l.business.name}  {l.business.website or '(no website)'}")


CATEGORY_LABELS = {
    "real_estate": "real estate agencies", "car_repair": "car repair shops", "car_dealer": "car dealers",
    "hvac": "HVAC companies", "restaurant": "restaurants", "cafe": "cafes", "salon": "salons",
    "spa": "spas", "gym": "gyms", "furniture": "furniture stores", "clothes": "clothing stores",
    "jewelry": "jewelry stores", "pharmacy": "pharmacies", "veterinary": "vets",
    "driving_school": "driving schools", "travel_agency": "travel agencies", "bakery": "bakeries",
    "school": "schools", "kindergarten": "kindergartens", "insurance": "insurance agencies",
}


def category_label(category: Optional[str]) -> str:
    if not category:
        return "local businesses"
    return CATEGORY_LABELS.get(category, category.replace("_", " ") + "s")


def cmd_search(args: argparse.Namespace) -> None:
    tags = parse_tags(args.tag) if args.tag else CATEGORIES.get(args.category or "")
    if not tags:
        raise SystemExit("Give --category (see `python lead_finder.py categories`) or --tag key=value")
    ua = f"lead-finder/1.0 ({args.contact_email or 'personal use'})"

    log(f"Finding {args.city!r} on the map...")
    place = geocode(args.city, ua)
    log(f"  -> {place.get('display_name')}")
    time.sleep(1)  # Nominatim usage policy: max 1 request per second

    log(f"Searching for {args.category or args.tag}...")
    elements = run_overpass(build_overpass_query(place, tags, args.limit), ua)
    if not args.include_chains:
        elements = [e for e in elements if not is_chain(e)]
    businesses, seen = [], set()
    for el in elements:
        b = element_to_business(el)
        key = (b.name.lower(), domain_of(b.website)) if b else None
        if b and key not in seen:
            seen.add(key)
            businesses.append(b)
    log(f"  Found {len(businesses)} businesses ({sum(1 for b in businesses if b.website)} with a website)")

    args.city_label = args.city.split(",")[0].strip()
    args.category_label = category_label(args.category)
    leads = build_leads(businesses, args)
    out = args.out or os.path.join("leads", f"{slug(args.category or 'custom')}_{slug(args.city)}")
    finish(leads, out, f"Leads: {args.category_label} in {args.city}")


def cmd_audit(args: argparse.Namespace) -> None:
    businesses = read_input_file(args.input)
    if not businesses:
        raise SystemExit(f"No websites found in {args.input}")
    args.city_label = args.city or ""
    args.category_label = args.category_label or ""
    leads = build_leads(businesses, args)
    out = args.out or os.path.join("leads", slug(os.path.splitext(os.path.basename(args.input))[0]))
    finish(leads, out, f"Website audit: {os.path.basename(args.input)}")


def cmd_categories(_: argparse.Namespace) -> None:
    for name, tags in CATEGORIES.items():
        print(f"  {name:<16} {', '.join(f'{k}={v}' for k, v in tags)}")


def main(argv: Optional[List[str]] = None) -> None:
    p = argparse.ArgumentParser(description="Find businesses with weak websites and draft outreach emails.")
    sub = p.add_subparsers(dest="cmd", required=True)

    def common(sp: argparse.ArgumentParser) -> None:
        sp.add_argument("--your-name", default=os.environ.get("LEAD_YOUR_NAME", "[Your Name]"),
                        help="Name used to sign the email drafts")
        sp.add_argument("--your-site", default=os.environ.get("LEAD_YOUR_SITE", ""),
                        help="Your portfolio URL, added under your name")
        sp.add_argument("--min-score", type=int, default=0, help="Hide leads scoring below this (0-100)")
        sp.add_argument("--skip-no-website", action="store_true", help="Leave out businesses without a website")
        sp.add_argument("--workers", type=int, default=8, help="Websites checked in parallel")
        sp.add_argument("--psi-key", default=os.environ.get("PSI_API_KEY", ""),
                        help="Google PageSpeed API key (optional, adds a mobile speed score)")
        sp.add_argument("--out", help="Output path without extension (default: leads/<name>)")

    s = sub.add_parser("search", help="Find businesses in a city and audit their websites")
    s.add_argument("--city", required=True, help='e.g. "Austin, Texas, USA"')
    s.add_argument("--category", choices=sorted(CATEGORIES), help="Business type")
    s.add_argument("--tag", action="append", help="Custom OpenStreetMap tag, e.g. shop=bicycle (repeatable)")
    s.add_argument("--limit", type=int, default=150, help="Max businesses to fetch")
    s.add_argument("--include-chains", action="store_true", help="Keep big brands/franchises")
    s.add_argument("--contact-email", default=os.environ.get("LEAD_CONTACT_EMAIL", ""),
                   help="Your email, sent to OpenStreetMap as their usage policy asks")
    common(s)
    s.set_defaults(func=cmd_search)

    a = sub.add_parser("audit", help="Audit websites from a .txt or .csv file")
    a.add_argument("--input", required=True, help="File with websites (see README)")
    a.add_argument("--city", default="", help="City mentioned in the emails (optional)")
    a.add_argument("--category-label", default="", help='e.g. "dentists" (used in the emails)')
    common(a)
    a.set_defaults(func=cmd_audit)

    c = sub.add_parser("categories", help="List built-in business categories")
    c.set_defaults(func=cmd_categories)

    args = p.parse_args(argv)
    args.func(args)


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        log("\nStopped.")

"""Offline tests: a local HTTP server stands in for real business websites."""

import argparse
import csv
import http.server
import os
import tempfile
import threading
import unittest

import lead_finder as lf

OLD_SITE = """<html><head><title>Smile Dental</title>
<meta name="generator" content="WordPress 5.2.4">
<script src="/wp-includes/js/jquery/jquery.js?ver=1.12.4"></script></head>
<body><a href="/contact-us">Contact</a><footer>&copy; 2017 - 2019 Smile Dental</footer></body></html>"""

CONTACT_PAGE = "<html><body>Email us: info@smiledental.test or logo@2x.png</body></html>"

GOOD_SITE = f"""<html><head><title>Modern Dental Care</title>
<meta name="viewport" content="width=device-width">
<meta name="description" content="Dentist">
<script async src="https://www.googletagmanager.com/gtag/js"></script></head>
<body>hello@modern.test <footer>© {lf.CURRENT_YEAR} Modern</footer></body></html>"""


class Handler(http.server.BaseHTTPRequestHandler):
    pages = {"/old": OLD_SITE, "/contact-us": CONTACT_PAGE, "/good": GOOD_SITE}

    def do_GET(self):
        if self.path not in self.pages:
            self.send_error(404)
            return
        body = self.pages[self.path].encode()
        self.send_response(200)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *args):
        pass


def ctx(**kw):
    base = dict(your_name="Rahim", your_site="https://rahim.dev", min_score=0, skip_no_website=False,
                workers=4, psi_key="", city_label="Austin", category_label="dentists")
    base.update(kw)
    return argparse.Namespace(**base)


class LeadFinderTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.server = http.server.ThreadingHTTPServer(("127.0.0.1", 0), Handler)
        threading.Thread(target=cls.server.serve_forever, daemon=True).start()
        cls.base = f"http://127.0.0.1:{cls.server.server_address[1]}"

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()

    def test_old_wordpress_site_has_many_issues(self):
        a = lf.audit_website(self.base + "/old")
        self.assertEqual(a.platform, "WordPress")
        self.assertEqual(a.wp_version, "5.2.4")
        self.assertEqual(a.copyright_year, 2019)
        for code in ("no_https", "no_viewport", "outdated_copyright", "old_wp", "old_jquery",
                     "no_meta_desc", "no_analytics"):
            self.assertIn(code, a.issues)
        self.assertEqual(a.emails, ["info@smiledental.test"])  # found on the contact page

    def test_good_site_scores_low(self):
        a = lf.audit_website(self.base + "/good")
        self.assertEqual(a.issues, ["no_https"])  # local server is plain http
        self.assertEqual(a.emails, ["hello@modern.test"])

    def test_down_site(self):
        a = lf.audit_website(self.base + "/missing")
        self.assertEqual(a.issues, ["down"])
        a = lf.audit_website("http://127.0.0.1:1")
        self.assertEqual(a.issues, ["down"])

    def test_leads_ranked_and_emails_drafted(self):
        businesses = [
            lf.Business("Modern Dental", website=self.base + "/good"),
            lf.Business("Smile Dental", website=self.base + "/old"),
            lf.Business("No Site Dental", phone="+1 512 555 0100"),
            lf.Business("Ghost Dental"),  # no website, no phone: skipped
        ]
        leads = lf.build_leads(businesses, ctx())
        self.assertEqual([l.business.name for l in leads], ["Smile Dental", "No Site Dental", "Modern Dental"])
        top = leads[0]
        self.assertEqual(top.score, 100)
        self.assertEqual(top.business.email, "info@smiledental.test")
        self.assertIn("Hi Smile Dental team", top.body)
        self.assertIn("dentists in Austin", top.body)
        self.assertIn("Rahim\nhttps://rahim.dev", top.body)
        self.assertEqual(top.body.count("\n- "), 3)
        self.assertIn("don't seem to have a website", leads[1].body)

        filtered = lf.build_leads(businesses, ctx(min_score=50, skip_no_website=True))
        self.assertEqual([l.business.name for l in filtered], ["Smile Dental"])

    def test_outputs(self):
        leads = lf.build_leads([lf.Business("Smile <Dental>", website=self.base + "/old")], ctx())
        with tempfile.TemporaryDirectory() as d:
            out = os.path.join(d, "sub", "leads")
            lf.finish(leads, out, "Test")
            with open(out + ".html", encoding="utf-8") as f:
                page = f.read()
            self.assertIn("Smile &lt;Dental&gt;", page)
            self.assertIn("mailto:info@smiledental.test?subject=", page)
            with open(out + ".csv", encoding="utf-8-sig") as f:
                rows = list(csv.DictReader(f))
            self.assertEqual(rows[0]["name"], "Smile <Dental>")
            self.assertEqual(rows[0]["score"], "100")

    def test_read_input_files(self):
        with tempfile.TemporaryDirectory() as d:
            txt = os.path.join(d, "sites.txt")
            with open(txt, "w") as f:
                f.write("# comment\nexample.com\nBob's Plumbing | https://bobs.test\n\n")
            got = lf.read_input_file(txt)
            self.assertEqual([(b.name, b.website) for b in got],
                             [("example.com", "example.com"), ("Bob's Plumbing", "https://bobs.test")])
            path = os.path.join(d, "sites.csv")
            with open(path, "w") as f:
                f.write("Name,Website,Phone\nAce Law,acelaw.test,123\nNo Site,,456\n")
            got = lf.read_input_file(path)
            self.assertEqual([(b.name, b.website, b.phone) for b in got], [("Ace Law", "acelaw.test", "123")])

    def test_overpass_query_and_elements(self):
        q = lf.build_overpass_query({"osm_type": "relation", "osm_id": 113314}, [("amenity", "dentist")], 50)
        self.assertIn("area(id:3600113314)->.a;", q)
        self.assertIn('nwr["amenity"="dentist"]["name"](area.a);', q)
        self.assertIn("out tags center 50;", q)
        q = lf.build_overpass_query({"osm_type": "node", "boundingbox": ["1", "2", "3", "4"]}, [("shop", "x")], 5)
        self.assertIn("(1,3,2,4)", q)

        el = {"type": "node", "id": 7, "tags": {
            "name": "Ace", "contact:website": "ace.test", "phone": "1", "addr:housenumber": "5",
            "addr:street": "Main St", "addr:city": "Austin"}}
        b = lf.element_to_business(el)
        self.assertEqual((b.website, b.phone, b.address), ("ace.test", "1", "5 Main St, Austin"))
        self.assertEqual(b.source_url, "https://www.openstreetmap.org/node/7")
        self.assertIsNone(lf.element_to_business({"type": "node", "id": 1, "tags": {}}))
        self.assertTrue(lf.is_chain({"tags": {"brand": "Starbucks"}}))

    def test_copyright_ignores_future_years(self):
        self.assertEqual(lf.latest_copyright_year("Copyright 2015-2099"), 2015)
        self.assertEqual(lf.latest_copyright_year("no year here"), 0)


if __name__ == "__main__":
    unittest.main()

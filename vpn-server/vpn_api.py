#!/usr/bin/env python3
"""Peer provisioning API for the Secure VPN app.

Runs next to WireGuard on each VPN server. The app generates its own key pair and
POSTs the public key to /register; this service adds it as a WireGuard peer and
returns the tunnel address assigned to it. Private keys never leave the phone.

Configuration comes from environment variables (see install.sh).
Python standard library only.
"""

import base64
import hmac
import ipaddress
import json
import os
import subprocess
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

IFACE = os.environ.get("WG_INTERFACE", "wg0")
SUBNET = ipaddress.ip_network(os.environ.get("WG_SUBNET", "10.8.0.0/16"))
DB_PATH = os.environ.get("VPN_API_DB", "/var/lib/vpn-api/peers.json")
TOKEN = os.environ.get("VPN_API_TOKEN", "")
PORT = int(os.environ.get("VPN_API_PORT", "8080"))
WG_BIN = os.environ.get("WG_BIN", "wg")
# Registrations allowed per client IP per hour.
RATE_LIMIT = int(os.environ.get("VPN_API_RATE_LIMIT", "30"))
# Peers that have not registered (i.e. connected) for this many days are removed.
STALE_DAYS = int(os.environ.get("VPN_API_STALE_DAYS", "90"))

lock = threading.Lock()
peers = {}  # public key -> {"ip": str, "last_seen": int}
hits = {}  # client ip -> [timestamps]


def load_peers():
    try:
        with open(DB_PATH) as f:
            return json.load(f)
    except FileNotFoundError:
        return {}


def save_peers():
    os.makedirs(os.path.dirname(DB_PATH), exist_ok=True)
    tmp = DB_PATH + ".tmp"
    with open(tmp, "w") as f:
        json.dump(peers, f)
    os.replace(tmp, DB_PATH)


def wg(*args):
    subprocess.run([WG_BIN, *args], check=True, capture_output=True)


def add_peer(public_key, ip):
    wg("set", IFACE, "peer", public_key, "allowed-ips", f"{ip}/32")


def valid_key(key):
    try:
        return len(base64.b64decode(key, validate=True)) == 32
    except (ValueError, TypeError):
        return False


def allocate_ip():
    used = {p["ip"] for p in peers.values()}
    hosts = SUBNET.hosts()
    next(hosts)  # the first address belongs to the server
    for host in hosts:
        if str(host) not in used:
            return str(host)
    raise RuntimeError("address pool exhausted")


def rate_limited(client_ip):
    now = time.time()
    recent = [t for t in hits.get(client_ip, []) if now - t < 3600]
    recent.append(now)
    hits[client_ip] = recent
    return len(recent) > RATE_LIMIT


def prune_stale():
    cutoff = time.time() - STALE_DAYS * 86400
    stale = [key for key, p in peers.items() if p["last_seen"] < cutoff]
    for key in stale:
        try:
            wg("set", IFACE, "peer", key, "remove")
        except subprocess.CalledProcessError:
            pass
        del peers[key]
    return bool(stale)


def register(public_key):
    with lock:
        changed = prune_stale()
        peer = peers.get(public_key)
        if peer is None:
            peer = {"ip": allocate_ip(), "last_seen": 0}
            peers[public_key] = peer
            changed = True
        add_peer(public_key, peer["ip"])
        # Only persist last_seen roughly daily to avoid a disk write per connect.
        now = int(time.time())
        if now - peer["last_seen"] > 86400:
            peer["last_seen"] = now
            changed = True
        if changed:
            save_peers()
        return f"{peer['ip']}/32"


class Handler(BaseHTTPRequestHandler):
    server_version = "vpn-api"

    def reply(self, code, body):
        data = json.dumps(body).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def do_GET(self):
        if self.path == "/ping":
            self.reply(200, {"ok": True})
        else:
            self.reply(404, {"error": "not found"})

    def do_POST(self):
        if self.path != "/register":
            return self.reply(404, {"error": "not found"})
        with lock:
            limited = rate_limited(self.client_address[0])
        if limited:
            return self.reply(429, {"error": "too many requests"})
        length = int(self.headers.get("Content-Length") or 0)
        if length <= 0 or length > 4096:
            return self.reply(400, {"error": "bad request"})
        try:
            body = json.loads(self.rfile.read(length))
        except ValueError:
            return self.reply(400, {"error": "bad request"})
        if not isinstance(body, dict):
            return self.reply(400, {"error": "bad request"})
        if TOKEN and not hmac.compare_digest(str(body.get("token", "")), TOKEN):
            return self.reply(403, {"error": "forbidden"})
        public_key = body.get("public_key")
        if not isinstance(public_key, str) or not valid_key(public_key):
            return self.reply(400, {"error": "invalid public key"})
        try:
            address = register(public_key)
        except RuntimeError as e:
            return self.reply(503, {"error": str(e)})
        except subprocess.CalledProcessError:
            return self.reply(500, {"error": "could not add peer"})
        self.reply(200, {"address": address})

    def log_message(self, fmt, *args):
        pass


def main():
    global peers
    peers = load_peers()
    with lock:
        if prune_stale():
            save_peers()
        for key, peer in peers.items():
            try:
                add_peer(key, peer["ip"])
            except subprocess.CalledProcessError as e:
                print(f"could not restore peer {key}: {e.stderr!r}", flush=True)
    print(f"vpn-api listening on :{PORT} for {IFACE} ({len(peers)} peers)", flush=True)
    ThreadingHTTPServer(("0.0.0.0", PORT), Handler).serve_forever()


if __name__ == "__main__":
    main()

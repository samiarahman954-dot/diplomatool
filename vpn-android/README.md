# Secure VPN (Android)

An Android VPN app built on the official [WireGuard](https://www.wireguard.com/) tunnel library.

- Home screen: one-tap connect, connection timer, live download/upload speed and totals
- Server list: country flags, search, live ping, pull-to-refresh
- "My servers": add your own WireGuard servers by QR code, `.conf` file or paste
- Ongoing notification with timer and a Disconnect button
- Settings: auto-connect, DNS choice (Cloudflare / Google / Quad9 / AdGuard), kill switch shortcut
- English and Bengali UI

## How servers work

```
 phone (app)                                   your VPS (vpn-server/)
 ───────────                                   ──────────────────────
 1. downloads server-list/servers.json  (HTTPS from GitHub)
 2. generates its own WireGuard key pair
 3. POST /register {public_key}   ───────────▶ vpn_api.py adds the peer to wg0,
                                  ◀─────────── returns {"address": "10.8.0.x/32"}
 4. connects WireGuard tunnel     ═══════════▶ WireGuard (UDP 51820) → internet
```

Every phone gets its own key and address, so any number of users can share a server.
The private key never leaves the phone.

## Adding a server

1. Rent a VPS (Ubuntu 22.04/24.04) in the country you want — DigitalOcean, Vultr, Hetzner, Linode, etc.
2. On the VPS:
   ```sh
   git clone https://github.com/samiarahman954-dot/diplomatool.git
   cd diplomatool
   sudo bash vpn-server/install.sh
   ```
3. The script prints a JSON entry. Paste it into the `servers` array of
   [`server-list/servers.json`](server-list/servers.json), set the name, city and country code, and push.
4. Apps pick up the new list on next launch (or pull-to-refresh on the server screen). No app update needed.

Example `servers.json` with two locations:

```json
{
  "servers": [
    {
      "id": "sg-1",
      "name": "Singapore",
      "city": "Singapore",
      "country": "SG",
      "endpoint": "203.0.113.10:51820",
      "publicKey": "<server public key>",
      "api": "http://203.0.113.10:8080",
      "token": "<token from install.sh>"
    },
    {
      "id": "de-1",
      "name": "Germany",
      "city": "Frankfurt",
      "country": "DE",
      "endpoint": "198.51.100.20:51820",
      "publicKey": "<server public key>",
      "api": "http://198.51.100.20:8080",
      "token": "<token from install.sh>"
    }
  ]
}
```

## Download the APK

Every push that touches `vpn-android/` is built by GitHub Actions (`.github/workflows/vpn-apk.yml`).
The APK is attached to the **`vpn-latest`** release on the repository's *Releases* page.

## Building locally

Requires JDK 17 and the Android SDK (platform 35):

```sh
cd vpn-android
./gradlew assembleDebug
# -> app/build/outputs/apk/debug/app-debug.apk
```

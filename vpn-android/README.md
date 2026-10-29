# Secure VPN (Android)

A simple Android VPN client built on the official [WireGuard](https://www.wireguard.com/) tunnel library.

## Download the APK

Every push that touches `vpn-android/` is built by GitHub Actions (`.github/workflows/vpn-apk.yml`).
The APK is attached to the **`vpn-latest`** release on the repository's *Releases* page
(and also uploaded as the `SecureVPN-apk` workflow artifact).

On the phone: open the release, download `SecureVPN.apk`, allow "Install unknown apps" for your browser, and install.

## Using it

A VPN client needs a VPN server. Get a WireGuard config from:

- your own server (e.g. a VPS running WireGuard — `wg-easy`, PiVPN, or plain `wg-quick`), or
- a VPN provider that offers WireGuard config files.

Then in the app: paste the config (or tap **Import .conf**) → **Connect** → accept Android's VPN permission prompt.

Example config shape:

```ini
[Interface]
PrivateKey = <client private key>
Address = 10.8.0.2/32
DNS = 1.1.1.1

[Peer]
PublicKey = <server public key>
Endpoint = your.server.com:51820
AllowedIPs = 0.0.0.0/0, ::/0
PersistentKeepalive = 25
```

## Building locally

Requires JDK 17 and the Android SDK (platform 35):

```sh
cd vpn-android
./gradlew assembleDebug
# -> app/build/outputs/apk/debug/app-debug.apk
```

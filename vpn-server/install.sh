#!/usr/bin/env bash
# Sets up a Secure VPN server on a fresh Ubuntu/Debian VPS:
#   WireGuard + NAT + the provisioning API that the app registers with.
#
# Usage (as root, from a checkout of this repository):
#   sudo bash vpn-server/install.sh
#
# Optional environment variables:
#   WG_PORT=51820   UDP port for WireGuard
#   API_PORT=8080   TCP port for the provisioning API
#   PUBLIC_IP=...   public IPv4 of this server (auto-detected)
#   VPN_API_TOKEN=  shared token the app sends (random by default)
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
  echo "Please run as root (sudo bash $0)" >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WG_PORT="${WG_PORT:-51820}"
API_PORT="${API_PORT:-8080}"
SUBNET="10.8.0.0/16"
SERVER_ADDR="10.8.0.1/16"

if [[ ! -f "$SCRIPT_DIR/vpn_api.py" ]]; then
  echo "vpn_api.py not found next to install.sh" >&2
  exit 1
fi

echo "==> Installing packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y wireguard iptables python3 curl

PUBLIC_IP="${PUBLIC_IP:-$(curl -4 -fsS https://api.ipify.org)}"
NIC="$(ip -4 route show default | awk '{print $5; exit}')"
echo "==> Public IP: $PUBLIC_IP, interface: $NIC"

echo "==> Enabling IP forwarding"
echo "net.ipv4.ip_forward=1" > /etc/sysctl.d/99-secure-vpn.conf
sysctl -q -p /etc/sysctl.d/99-secure-vpn.conf

echo "==> Configuring WireGuard"
umask 077
mkdir -p /etc/wireguard
[[ -f /etc/wireguard/server.key ]] || wg genkey > /etc/wireguard/server.key
SERVER_PRIVATE_KEY="$(cat /etc/wireguard/server.key)"
SERVER_PUBLIC_KEY="$(echo "$SERVER_PRIVATE_KEY" | wg pubkey)"

# Peers are not stored here: the API re-adds them from its database on start.
# Clients may reach the internet but not each other.
cat > /etc/wireguard/wg0.conf <<CONF
[Interface]
Address = $SERVER_ADDR
ListenPort = $WG_PORT
PrivateKey = $SERVER_PRIVATE_KEY
PostUp = iptables -t nat -A POSTROUTING -s $SUBNET -o $NIC -j MASQUERADE; iptables -I FORWARD -o %i -j ACCEPT; iptables -I FORWARD -i %i -j ACCEPT; iptables -I FORWARD -i %i -o %i -j DROP
PostDown = iptables -t nat -D POSTROUTING -s $SUBNET -o $NIC -j MASQUERADE; iptables -D FORWARD -o %i -j ACCEPT; iptables -D FORWARD -i %i -j ACCEPT; iptables -D FORWARD -i %i -o %i -j DROP
CONF

echo "==> Installing provisioning API"
install -d /usr/local/lib/vpn-api /var/lib/vpn-api
install -m 755 "$SCRIPT_DIR/vpn_api.py" /usr/local/lib/vpn-api/vpn_api.py

if [[ -f /etc/vpn-api.env ]]; then
  # Keep the existing token so already-published server lists keep working.
  EXISTING_TOKEN="$(sed -n 's/^VPN_API_TOKEN=//p' /etc/vpn-api.env)"
fi
VPN_API_TOKEN="${VPN_API_TOKEN:-${EXISTING_TOKEN:-$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')}}"
cat > /etc/vpn-api.env <<ENV
WG_INTERFACE=wg0
WG_SUBNET=$SUBNET
VPN_API_PORT=$API_PORT
VPN_API_TOKEN=$VPN_API_TOKEN
ENV

cat > /etc/systemd/system/vpn-api.service <<UNIT
[Unit]
Description=Secure VPN provisioning API
Requires=wg-quick@wg0.service
After=wg-quick@wg0.service
PartOf=wg-quick@wg0.service

[Service]
EnvironmentFile=/etc/vpn-api.env
ExecStart=/usr/bin/python3 /usr/local/lib/vpn-api/vpn_api.py
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
UNIT

echo "==> Opening firewall ports"
if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
  ufw allow "$WG_PORT/udp"
  ufw allow "$API_PORT/tcp"
fi

echo "==> Starting services"
systemctl daemon-reload
systemctl enable wg-quick@wg0 vpn-api >/dev/null
systemctl restart wg-quick@wg0
systemctl restart vpn-api

sleep 1
if curl -fsS "http://127.0.0.1:$API_PORT/ping" >/dev/null; then
  echo "==> API is up"
else
  echo "!! API did not respond; check: journalctl -u vpn-api" >&2
fi

cat <<DONE

============================================================
Server ready. Add this entry to vpn-android/server-list/servers.json
(edit name, city and the 2-letter country code):

    {
      "id": "server-${PUBLIC_IP//./-}",
      "name": "Country name",
      "city": "City",
      "country": "XX",
      "endpoint": "$PUBLIC_IP:$WG_PORT",
      "publicKey": "$SERVER_PUBLIC_KEY",
      "api": "http://$PUBLIC_IP:$API_PORT",
      "token": "$VPN_API_TOKEN"
    }

Also open UDP $WG_PORT and TCP $API_PORT in your cloud provider's firewall
(security group) if it has one.
============================================================
DONE

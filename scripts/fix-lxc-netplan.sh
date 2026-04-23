#!/usr/bin/env bash
# Fix Ubuntu LXC eth0 stuck DOWN. Ubuntu's netplan may not apply Proxmox's config; this applies
# manual ip/route + systemd service at boot. Run inside container with LXC_IP and LXC_GW env vars.

IP="${LXC_IP:-${1:?Need LXC_IP (e.g. 87.117.209.196/26)}}"
GW="${LXC_GW:-${2:?Need LXC_GW (e.g. 87.117.209.193)}}"
GW_IP="${GW%%/*}"

echo "Configuring eth0: $IP via $GW_IP"

mkdir -p /etc/netplan/backup
for f in /etc/netplan/*.yaml /etc/netplan/*.yml; do
  [[ -f "$f" ]] && mv "$f" /etc/netplan/backup/ 2>/dev/null || true
done

ip link set eth0 up 2>/dev/null || true
ip addr flush dev eth0 2>/dev/null || true
ip addr add "$IP" dev eth0
ip route add default via "$GW_IP" 2>/dev/null || true

echo "Checking interfaces..."
ip -br addr show eth0
ip route show
echo ""

SVC_FILE="/etc/systemd/system/lxc-eth0-fix.service"
cat > "$SVC_FILE" << EOF
[Unit]
Description=Bring up eth0 in LXC (netplan bypass)
After=network-pre.target
Before=network-online.target

[Service]
Type=oneshot
ExecStart=/bin/sh -c 'ip link set eth0 up 2>/dev/null; ip addr flush dev eth0 2>/dev/null; ip addr add $IP dev eth0; ip route add default via $GW_IP 2>/dev/null'
RemainAfterExit=yes

[Install]
WantedBy=multi-user.target
EOF
chmod 644 "$SVC_FILE"
systemctl daemon-reload 2>/dev/null || true
systemctl enable lxc-eth0-fix.service 2>/dev/null || true

echo "Installed $SVC_FILE (runs at boot)"
echo ""
echo "Testing gateway ($GW_IP)..."
if ping -c 1 -W 2 "$GW_IP" &>/dev/null; then
  echo "  Gateway reachable."
else
  echo "  Gateway unreachable - check host ip_forward and firewall."
fi
echo "Testing 8.8.8.8..."
ping -c 1 -W 3 8.8.8.8 &>/dev/null && echo "  OK" || echo "  FAILED (gateway may need forwarding/NAT)"

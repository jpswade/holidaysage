#!/usr/bin/env bash
# Upsert apex + www A records in Route 53 for ROUTE53_DOMAIN → given public IPv4.
# Requires: AWS CLI, jq; hosted zone for the domain must already exist in Route 53.
# Env file: scripts/aws/register-domain.env (ROUTE53_DOMAIN, AWS_PROFILE, AWS_REGION).
# Required: DNS_A_TARGET_IPV4 (e.g. 87.117.209.197 — no CIDR suffix).
#
#   export DNS_A_TARGET_IPV4=87.117.209.197
#   ./scripts/aws/route53-upsert-a-records.sh
#   DRY_RUN=1 ./scripts/aws/route53-upsert-a-records.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ROUTE53_ENV_FILE:-$SCRIPT_DIR/register-domain.env}"
# shellcheck source=/dev/null
set -a && source "$ENV_FILE" && set +a

die() { echo "error: $*" >&2; exit 1; }

IP="${DNS_A_TARGET_IPV4:-}"
[[ -n "$IP" ]] || die "set DNS_A_TARGET_IPV4 to the public IPv4 (no /mask), same host you use in DEPLOY_LXC_IP"
[[ "$IP" =~ ^[0-9.]+$ ]] || die "DNS_A_TARGET_IPV4 must look like an IPv4 address"
[[ -n "${ROUTE53_DOMAIN:-}" ]] || die "ROUTE53_DOMAIN must be set in ${ENV_FILE}"

ZONE_ID_FULL=$(aws route53 list-hosted-zones-by-name \
  --dns-name "${ROUTE53_DOMAIN}." \
  --query "HostedZones[?Name=='${ROUTE53_DOMAIN}.'].Id | [0]" \
  --output text 2>/dev/null | tr -d '\r')
[[ -n "$ZONE_ID_FULL" && "$ZONE_ID_FULL" != "None" ]] || die "no Route 53 hosted zone named ${ROUTE53_DOMAIN}. — create the zone (or delegate NS) first"
ZONE_ID="${ZONE_ID_FULL##*/hostedzone/}"

apex="${ROUTE53_DOMAIN}."
www="www.${ROUTE53_DOMAIN}."

batch=$(jq -nc \
  --arg apex "$apex" \
  --arg www "$www" \
  --arg ip "$IP" \
  '{
    Comment: "Upsert apex and www A",
    Changes: [
      { Action: "UPSERT", ResourceRecordSet: { Name: $apex, Type: "A", TTL: 300, ResourceRecords: [{ Value: $ip }] } },
      { Action: "UPSERT", ResourceRecordSet: { Name: $www, Type: "A", TTL: 300, ResourceRecords: [{ Value: $ip }] } }
    ]
  }')

if [[ -n "${DRY_RUN:-}" ]] || [[ "${1:-}" == "--dry-run" ]]; then
  echo "DRY_RUN: would apply to zone ${ZONE_ID} (${ROUTE53_DOMAIN} → ${IP})"
  echo "$batch" | jq .
  exit 0
fi

aws route53 change-resource-record-sets --hosted-zone-id "$ZONE_ID" --change-batch "$batch" >/dev/null
echo "OK: A records for ${apex} and ${www} → ${IP} (propagation may take a few minutes)"

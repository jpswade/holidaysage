#!/usr/bin/env bash
# Point the domain's registrar nameservers at Cloudflare (Route 53 Domains API).
# This replaces the current delegation set in one step — you do not delete AWS NS one by one.
#
# Prerequisites: AWS CLI v2, jq, and credentials for the profile (same as register-domain.sh).
#
# Defaults match a typical Cloudflare pair; override if your zone shows different hosts.
#
# Usage:
#   ./scripts/aws/update-nameservers-cloudflare.sh
#   DRY_RUN=1 ./scripts/aws/update-nameservers-cloudflare.sh
#   ./scripts/aws/update-nameservers-cloudflare.sh --dry-run
#
# Env file: ROUTE53_ENV_FILE (default: scripts/aws/register-domain.env) for ROUTE53_DOMAIN, AWS_PROFILE, AWS_REGION.
# Overrides:
#   CLOUDFLARE_NS_1, CLOUDFLARE_NS_2 — or CLOUDFLARE_NAMESERVERS="host1 host2 ..." (2–6 hosts per AWS docs for delegation)

set -euo pipefail

die() {
  echo "error: $*" >&2
  exit 1
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ROUTE53_ENV_FILE:-$SCRIPT_DIR/register-domain.env}"

[[ -f "$ENV_FILE" ]] || die "env file not found: ${ENV_FILE}"

# Command-line / exported overrides win over values in the env file (bash 3.2–safe).
[[ -n "${ROUTE53_DOMAIN+x}" ]] && _o_ROUTE53_DOMAIN=$ROUTE53_DOMAIN
[[ -n "${AWS_PROFILE+x}" ]] && _o_AWS_PROFILE=$AWS_PROFILE
[[ -n "${AWS_REGION+x}" ]] && _o_AWS_REGION=$AWS_REGION

set -a
# shellcheck source=register-domain.env
source "$ENV_FILE"
set +a

[[ -n "${_o_ROUTE53_DOMAIN+x}" ]] && ROUTE53_DOMAIN=$_o_ROUTE53_DOMAIN
[[ -n "${_o_AWS_PROFILE+x}" ]] && AWS_PROFILE=$_o_AWS_PROFILE
[[ -n "${_o_AWS_REGION+x}" ]] && AWS_REGION=$_o_AWS_REGION
unset _o_ROUTE53_DOMAIN _o_AWS_PROFILE _o_AWS_REGION 2>/dev/null || true

for arg in "$@"; do
  if [[ "$arg" == "--dry-run" ]]; then
    DRY_RUN=1
  fi
done

ROUTE53_DOMAIN="${ROUTE53_DOMAIN:-}"
[[ -n "$ROUTE53_DOMAIN" ]] || die "ROUTE53_DOMAIN must be set in ${ENV_FILE}"

AWS_PROFILE="${AWS_PROFILE:-jpswade}"
AWS_REGION="${AWS_REGION:-us-east-1}"
export AWS_PROFILE
export AWS_DEFAULT_REGION="$AWS_REGION"

# Defaults: pair from your Cloudflare zone; must match what the dashboard shows for that zone.
CLOUDFLARE_NS_1="${CLOUDFLARE_NS_1:-anna.ns.cloudflare.com}"
CLOUDFLARE_NS_2="${CLOUDFLARE_NS_2:-theo.ns.cloudflare.com}"

command -v aws >/dev/null 2>&1 || die "aws CLI not found"
command -v jq >/dev/null 2>&1 || die "jq not found"

ns_args=()
if [[ -n "${CLOUDFLARE_NAMESERVERS:-}" ]]; then
  read -r -a _ns_arr <<<"$CLOUDFLARE_NAMESERVERS"
  for h in "${_ns_arr[@]}"; do
    [[ -n "$h" ]] || continue
    ns_args+=(Name="$h")
  done
else
  ns_args+=(Name="$CLOUDFLARE_NS_1" Name="$CLOUDFLARE_NS_2")
fi

((${#ns_args[@]} >= 2)) || die "need at least two nameservers (set CLOUDFLARE_NS_1/2 or CLOUDFLARE_NAMESERVERS)"

echo "Using env file: ${ENV_FILE}"
echo "Domain: ${ROUTE53_DOMAIN} (profile=${AWS_PROFILE}, region=${AWS_REGION})"

echo "Current registrar nameservers:"
aws route53domains get-domain-detail \
  --domain-name "$ROUTE53_DOMAIN" \
  --no-cli-pager \
  --output json \
  | jq -r '.Nameservers // [] | .[] | .Name'

echo "New nameservers (${#ns_args[@]} entries):"
for entry in "${ns_args[@]}"; do
  [[ "$entry" == Name=* ]] || die "internal error: expected Name=host, got: ${entry}"
  echo "  ${entry#Name=}"
done

if [[ "${DRY_RUN:-0}" == "1" ]]; then
  echo "DRY_RUN=1: not calling update-domain-nameservers."
  exit 0
fi

echo "Submitting update-domain-nameservers..."
aws route53domains update-domain-nameservers \
  --domain-name "$ROUTE53_DOMAIN" \
  --nameservers "${ns_args[@]}" \
  --no-cli-pager

echo "Request accepted. Propagation can take up to 48 hours; track in Route 53 Domains or get-operation-detail if an operation ID was returned."

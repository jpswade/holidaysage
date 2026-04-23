#!/usr/bin/env bash
# Register a domain via Amazon Route 53 Domains (see register-domain.env).
#
# Prerequisites: AWS CLI v2, jq, and credentials for the profile in register-domain.env.
# Registrant details: scripts/aws/route53-registrant.json (gitignored — copy from route53-registrant.example.json).
#   Override: ROUTE53_REGISTRANT_JSON=/path/to/file.json
#
# Usage:
#   ./scripts/aws/register-domain.sh
#   DRY_RUN=1 ./scripts/aws/register-domain.sh
#   ./scripts/aws/register-domain.sh --dry-run

set -euo pipefail

die() {
  echo "error: $*" >&2
  exit 1
}

# Route 53 RegisterDomain expects "+CC.subscriber" (documented as resembling +999.12345678), not compact E.164.
route53_phone_number() {
  local p="$1"
  if [[ "$p" =~ ^\+[0-9]+\.[0-9]+$ ]]; then
    printf '%s' "$p"
    return
  fi
  if [[ "$p" =~ ^\+44[0-9]{9,10}$ ]]; then
    printf '+44.%s' "${p:3}"
    return
  fi
  if [[ "$p" =~ ^\+1[2-9][0-9]{9}$ ]]; then
    printf '+1.%s' "${p:2}"
    return
  fi
  die "phone_e164 must use Route 53 format +CC.subscriber (dot after country code), e.g. +44.7709790865. Got: $p"
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${ROUTE53_ENV_FILE:-$SCRIPT_DIR/register-domain.env}"

[[ -f "$ENV_FILE" ]] || die "env file not found: ${ENV_FILE}"

# Command-line / exported overrides win over values in the env file (bash 3.2–safe).
[[ -n "${ROUTE53_DOMAIN+x}" ]] && _o_ROUTE53_DOMAIN=$ROUTE53_DOMAIN
[[ -n "${AWS_PROFILE+x}" ]] && _o_AWS_PROFILE=$AWS_PROFILE
[[ -n "${AWS_REGION+x}" ]] && _o_AWS_REGION=$AWS_REGION
[[ -n "${DURATION_YEARS+x}" ]] && _o_DURATION_YEARS=$DURATION_YEARS

set -a
# shellcheck source=register-domain.env
source "$ENV_FILE"
set +a

[[ -n "${_o_ROUTE53_DOMAIN+x}" ]] && ROUTE53_DOMAIN=$_o_ROUTE53_DOMAIN
[[ -n "${_o_AWS_PROFILE+x}" ]] && AWS_PROFILE=$_o_AWS_PROFILE
[[ -n "${_o_AWS_REGION+x}" ]] && AWS_REGION=$_o_AWS_REGION
[[ -n "${_o_DURATION_YEARS+x}" ]] && DURATION_YEARS=$_o_DURATION_YEARS
unset _o_ROUTE53_DOMAIN _o_AWS_PROFILE _o_AWS_REGION _o_DURATION_YEARS 2>/dev/null || true

ROUTE53_REGISTRANT_JSON="${ROUTE53_REGISTRANT_JSON:-$SCRIPT_DIR/route53-registrant.json}"

[[ -n "${ROUTE53_DOMAIN:-}" ]] || die "ROUTE53_DOMAIN must be set in ${ENV_FILE}"

for arg in "$@"; do
  if [[ "$arg" == "--dry-run" ]]; then
    DRY_RUN=1
  fi
done

command -v aws >/dev/null 2>&1 || die "aws CLI not found"
command -v jq >/dev/null 2>&1 || die "jq not found"

[[ -f "$ROUTE53_REGISTRANT_JSON" ]] || die "contact file not found: ${ROUTE53_REGISTRANT_JSON} (copy scripts/aws/route53-registrant.example.json)"

AWS_PROFILE="${AWS_PROFILE:-jpswade}"
AWS_REGION="${AWS_REGION:-us-east-1}"
DURATION_YEARS="${DURATION_YEARS:-1}"

[[ "$DURATION_YEARS" =~ ^[1-9][0-9]*$ ]] || die "DURATION_YEARS must be a positive integer"
((DURATION_YEARS <= 10)) || die "DURATION_YEARS must be at most 10 for most TLDs"

# Validate required keys in JSON
for key in first_name last_name email phone_e164 address_line1 city postcode; do
  v="$(jq -r --arg k "$key" '.[$k] // empty' "$ROUTE53_REGISTRANT_JSON")"
  [[ -n "$v" ]] || die "missing or empty field in JSON: ${key}"
done

phone_raw="$(jq -r '.phone_e164' "$ROUTE53_REGISTRANT_JSON")"
phone="$(route53_phone_number "$phone_raw")"

CONTACT_JSON="$(jq --arg phone "$phone" '
  . as $in
  | ($in.uk_contact_type // (if ($in.country_code // "GB") == "GB" then "IND" else "FIND" end)) as $uktype
  | {
      FirstName: $in.first_name,
      LastName: $in.last_name,
      ContactType: ($in.contact_type // "PERSON"),
      OrganizationName: ($in.organization_name // ""),
      AddressLine1: $in.address_line1,
      AddressLine2: ($in.address_line2 // ""),
      City: $in.city,
      State: ($in.state // ""),
      CountryCode: ($in.country_code // "GB"),
      ZipCode: $in.postcode,
      PhoneNumber: $phone,
      Email: $in.email,
      Fax: "",
      ExtraParams: (
        [{Name: "UK_CONTACT_TYPE", Value: $uktype}]
        + (if (($in.uk_company_number // "") | tostring | length) > 0
           then [{Name: "UK_COMPANY_NUMBER", Value: ($in.uk_company_number | tostring)}]
           else [] end)
      )
    }
' "$ROUTE53_REGISTRANT_JSON")"

export AWS_PROFILE
export AWS_DEFAULT_REGION="$AWS_REGION"

echo "Using env file: ${ENV_FILE}"
echo "Using registrant JSON: ${ROUTE53_REGISTRANT_JSON}"
echo "Checking availability for ${ROUTE53_DOMAIN} (profile=${AWS_PROFILE}, region=${AWS_REGION})..."
avail_json="$(aws route53domains check-domain-availability \
  --domain-name "$ROUTE53_DOMAIN" \
  --no-cli-pager)"
avail="$(echo "$avail_json" | jq -r '.Availability // empty')"
[[ -n "$avail" ]] || die "unexpected check-domain-availability response: $avail_json"

echo "Availability: ${avail}"
if [[ "$avail" != "AVAILABLE" ]]; then
  die "domain is not available for new registration (${avail})"
fi

if [[ "${DRY_RUN:-0}" == "1" ]]; then
  echo "DRY_RUN=1: skipping register-domain (no charge)."
  exit 0
fi

INPUT_JSON="$(jq -n \
  --arg domain "$ROUTE53_DOMAIN" \
  --argjson years "$DURATION_YEARS" \
  --argjson contact "$CONTACT_JSON" \
  '{
    DomainName: $domain,
    DurationInYears: $years,
    AutoRenew: true,
    AdminContact: $contact,
    RegistrantContact: $contact,
    TechContact: $contact,
    PrivacyProtectAdminContact: true,
    PrivacyProtectRegistrantContact: true,
    PrivacyProtectTechContact: true
  }')"

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
printf '%s\n' "$INPUT_JSON" >"$tmp"

echo "Submitting register-domain (this charges your AWS account)..."
aws route53domains register-domain \
  --cli-input-json "file://${tmp}" \
  --no-cli-pager

echo "Request accepted. Track progress in the Route 53 console or with get-operation-detail using the operation ID returned above."

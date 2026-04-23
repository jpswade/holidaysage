# HolidaySage deployment

Production follows the same **LXC + git hoist** model as [DomainSage](https://github.com/jpswade/domainsage): Ubuntu in a Proxmox CT, **nginx + PHP 8.3 + MariaDB + Redis**, **Laravel Horizon** for queues, **Caddy** optional on ports 80/443 for Let’s Encrypt TLS.

## Layout on the server

| Path | Role |
|------|------|
| `$DEPLOY_BASE/staging` | Fresh `git clone` target |
| `$DEPLOY_BASE/current` | Live application |
| `$DEPLOY_BASE/storage` | Shared Laravel `storage` tree |

`DEPLOY_BASE` defaults to `DEPLOY_LXC_PATH` (e.g. `/var/www/holidaysage`).

## One-time: LXC stack (Proxmox)

1. Copy [`.env.deploy.example`](../.env.deploy.example) to `.env.deploy` and set `DEPLOY_LXC_*` (CT id, path, **static IP with CIDR**, gateway, bridge, storage), `DEPLOY_SERVER_*` (Proxmox or jump host: user, port, path to the bind-mount directory on the host), and `DEPLOY_VCS_REPO=git@github.com:jpswade/holidaysage.git`. Use the same network pattern as your other LXCs (e.g. DomainSage / Road on `dawn.phurix.com`).
2. **`.env.live`** in the project root (gitignored) is **required** for [`scripts/run-setup-lxc-on-host.sh`](../scripts/run-setup-lxc-on-host.sh): it is copied to the host as `.env` for the bind-mounted app. Create it from production secrets before the first run.
3. From your laptop, with AWS/SSH access as needed:

   ```bash
   ./scripts/run-setup-lxc-on-host.sh
   ```

   This script (mirroring DomainSage) creates the CT if missing via [`scripts/setup-proxmox-lxc.sh`](../scripts/setup-proxmox-lxc.sh) (Ubuntu 24 LXC, bind mount, static IP), starts it, `rsync`s the tree to `DEPLOY_SERVER_PATH`, copies `.env.live`, optional [`scripts/fix-lxc-netplan.sh`](../scripts/fix-lxc-netplan.sh) when `DEPLOY_LXC_GW` is set, runs `setup-lxc-app.sh` inside the CT, and appends a deploy public key to `root`’s `authorized_keys`.

   **Prerequisites on the Proxmox host:** an Ubuntu 24.04 LXC template (see comments in `setup-proxmox-lxc.sh` for `pveam` commands).

   If you prefer a manual CT, bind-mount the app path, copy `.env`, then run `setup-lxc-app.sh` inside the CT as below.

4. Inside the CT, from the app tree, run as root (the orchestration script already does this once):

   ```bash
   DEPLOY_GIT_DEPLOY=1 ./scripts/setup-lxc-app.sh
   ```

   This installs nginx (site `holidaysage`), MariaDB, **Redis**, PHP extensions including `php8.3-redis`, Node 20, systemd units **`holidaysage-horizon`** and **`holidaysage-scheduler`**.

5. Edit `.env` on the server: `APP_URL`, `APP_KEY`, `DB_*`, `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `REDIS_HOST=127.0.0.1`. For TLS termination in front of nginx, set `TRUSTED_PROXY_ALL=true` (see [AppServiceProvider](../app/Providers/AppServiceProvider.php)).

## Deploy from your laptop

```bash
./scripts/setup-deploy-key.sh          # creates ~/.ssh/holidaysage-deploy, adds GitHub deploy key
./scripts/add-deploy-key-to-container.sh
./scripts/deploy.sh                    # or ./scripts/deploy.sh --no-push
```

`deploy.sh` uses `LXC_SSH_PRIVATE_KEY_PATH` (default `~/.ssh/holidaysage-deploy`) to SSH to the CT and copies the same key (or `DEPLOY_KEY_PATH`) for `git clone`.

Set `DEPLOY_NGINX_SITE` in `.env.deploy` if you changed the nginx site filename (default `holidaysage`).

## HTTPS (Caddy)

After **DNS for `holidaysage.co.uk`** points at the host/CT and ports **80/443** are open:

```bash
./scripts/run-setup-https-caddy-on-host.sh
```

Inside the CT you can run `./scripts/setup-https-caddy-lxc.sh` directly. Override domains with `CADDY_SITE_DOMAIN` / `CADDY_SITE_WWW_DOMAIN`.

Then set `APP_URL=https://holidaysage.co.uk` in production `.env`.

## GitHub Actions

Workflow: [`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml). Non-secret CT settings: [`.github/deploy-config.env`](../.github/deploy-config.env) (update IPs/CT id for your environment). If `DEPLOY_VCS_REPO` is not set there, the workflow appends `git@github.com:<owner>/<repo>.git` from the GitHub context so forks and renames stay correct.

| Secret | Purpose |
|--------|---------|
| `LXC_SSH_PRIVATE_KEY` | SSH private key; matching **public** key on the container `authorized_keys`. |
| `ENV_PRODUCTION` | Full production `.env` (multi-line), copied to `.env.live` before deploy. |
| `DEPLOY_KEY` (optional) | Private key for `git clone` inside the CT if different from LXC SSH key. |

## Register `holidaysage.co.uk` (AWS Route 53)

Same pattern as [DomainSage](https://github.com/jpswade/domainsage): use **Route 53 Domains** and the registration script in this repo.

**Prerequisites:** [AWS CLI v2](https://aws.amazon.com/cli/), `jq`, and a profile with permission to use `route53domains` (e.g. the same `jpswade` profile you use elsewhere).

1. **Registrant contact (not in git)**  
   Copy the example and edit with your real Nominet/Route 53–valid details (phone must be `+44.xxxxx` style for UK — see the script comments):

   ```bash
   cp scripts/aws/route53-registrant.example.json scripts/aws/route53-registrant.json
   ```

   `route53-registrant.json` is **gitignored**. You can reuse the same file you already use for DomainSage if the registrant is identical:  
   `cp /path/to/DomainSage/scripts/aws/route53-registrant.json scripts/aws/route53-registrant.json`

2. **Env** — [scripts/aws/register-domain.env](../scripts/aws/register-domain.env) sets `ROUTE53_DOMAIN=holidaysage.co.uk`, `AWS_PROFILE`, and `AWS_REGION` (default `us-east-1` for the Route 53 Domains API). Adjust the profile if needed.

3. **Check only (no charge):**

   ```bash
   chmod +x scripts/aws/register-domain.sh
   DRY_RUN=1 ./scripts/aws/register-domain.sh
   # or: ./scripts/aws/register-domain.sh --dry-run
   ```

4. **Register (charges your AWS account):**

   ```bash
   ./scripts/aws/register-domain.sh
   ```

5. **DNS** — Either:
   - **Route 53 hosted zone:** in the [Route 53 console](https://console.aws.amazon.com/route53/), open the hosted zone and create an **A** record for `@` (and `www` if required) to the **public IPv4** that reaches the app (CT or host, same as your other sites), **or**
   - **Cloudflare:** add `holidaysage.co.uk` in Cloudflare, then point the **registrar** nameservers at Cloudflare (see below). Create **A** / **CNAME** records in Cloudflare to that same public IP.

6. **TLS & app** — After DNS propagates, run the Caddy step above, then set `APP_URL=https://holidaysage.co.uk` and `TRUSTED_PROXY_ALL=true` in production `.env`.

### Point Route 53 registration at Cloudflare nameservers

If the domain is registered through **Route 53 Domains** and you want Cloudflare to be **authoritative** for DNS at the registry:

1. Add the site in the [Cloudflare](https://dash.cloudflare.com/) dashboard and copy the **two nameservers** assigned to the zone.
2. If they differ from the defaults in the script, set `CLOUDFLARE_NS_1` and `CLOUDFLARE_NS_2` (or `CLOUDFLARE_NAMESERVERS="ns1 ns2"`) in the environment when you run the updater.
3. From the repo (uses [scripts/aws/register-domain.env](../scripts/aws/register-domain.env) for `ROUTE53_DOMAIN` and AWS credentials):

   ```bash
   chmod +x scripts/aws/update-nameservers-cloudflare.sh
   DRY_RUN=1 ./scripts/aws/update-nameservers-cloudflare.sh
   ./scripts/aws/update-nameservers-cloudflare.sh
   ```

   Propagation at the registry can take many hours. After delegation works, manage **A** / **AAAA** / **CNAME** in Cloudflare only.

## Provider import (Jet2 / TUI)

HolidaySage provider behaviour and env vars are specified in [HolidaySage_Cursor_Ready_Build_Spec.md](HolidaySage_Cursor_Ready_Build_Spec.md) (and eventually `config/holidaysage.php` when added). **Jet2** uses a **smart-search HTTP GET**; **TUI** **POSTs** the **ListOffer** GraphQL operation (see below). PHPUnit should fake both HTTP calls so the test suite does not call third parties.

| Variable | Values | Purpose |
|----------|--------|---------|
| `HOLIDAYSAGE_JET2_SEARCH_RESULTS_SEED_CSV_PATH` | Path to a CSV with a **`URL`** column | **Optional seed only:** the **first** row whose URL is a Jet2 **`/search/results`** link is used when a saved search has no `provider_import_url` (demo/ops). Example: [`resources/seeds/jet2_search_results_seed.example.csv`](../resources/seeds/jet2_search_results_seed.example.csv). Often the same file as bulk-import **`searches.csv`**. |
| `HOLIDAYSAGE_JET2_HTTP_TIMEOUT`, `HOLIDAYSAGE_JET2_HTTP_RETRIES`, `HOLIDAYSAGE_JET2_HTTP_RETRY_SLEEP_MS` | Integers | Optional tuning for live Jet2 GETs. |
| `HOLIDAYSAGE_TUI_GRAPHQL_URL` | HTTPS URL | ListOffer GraphQL endpoint (default `https://mwa.tui.com/search/mwa/search-results-mfe/graphql`). |
| `HOLIDAYSAGE_TUI_LIST_OFFER_REQUEST_JSON` | Path to JSON `{ "query": "…", "variables_template": { "params": … } }` | Defaults to `resources/providers/tui_list_offer_request.json` in the app tree; variables are merged with the saved search (airport, dates, party, duration range). |
| `HOLIDAYSAGE_TUI_ROOMS_STRING` | TUI pipe-encoded `rooms` | **Required** when the search includes **children** (unless you rely on a customised request JSON). |
| `HOLIDAYSAGE_TUI_HTTP_TIMEOUT`, `HOLIDAYSAGE_TUI_HTTP_RETRIES`, … | Integers | Optional tuning for live TUI POSTs. |

**Jet2:** for each saved search, store the Jet2 **search results** URL from the browser (`/search/results?…` on `jet2holidays.com`) in **`provider_import_url`**. The importer derives the smart-search API URL from that link. If the search has no URL yet, **`HOLIDAYSAGE_JET2_SEARCH_RESULTS_SEED_CSV_PATH`** may point at a small CSV with the same **`URL`** column as **`holidaysage:import-url-lists`** so the **first matching** `/search/results` row is used as a seed — not a substitute for per-search links in normal use.

**TUI:** the importer builds a **ListOffer** request from **`HOLIDAYSAGE_TUI_LIST_OFFER_REQUEST_JSON`** and the saved search, then POSTs to **`HOLIDAYSAGE_TUI_GRAPHQL_URL`**. Each travelling child’s **age (years)** is stored on the saved search (`child_ages`) and sent as `childrenAge[]` — not via environment variables. Sample response shapes for automated tests live under **`tests/fixtures/providers/tui/`** and are not loaded via application env.

**Operational notes:** match realistic **browser headers** and (for TUI) a plausible **Referer**; some stacks return **403** without them. Rate-limit scheduled refreshes so hourly jobs do not overload provider sites. Never commit cookies or auth tokens—use `.env` / `ENV_PRODUCTION` only.

**Operator debugging:** `php artisan holidaysage:provider-probe {search_slug} {jet2|tui}` runs importer + envelope parser once (no queue).

### Provider URL list CSVs (bulk ingest)

Each CSV row is one provider URL; the importer creates **one draft `SavedHolidaySearch` per row**, prefilling criteria where the URL parser can infer them (optional **`airport`** hints on hotel rows).

**`searches.csv`** uses a **`URL`** column (Jet2 `/search/results` links). **`hotels.csv`** uses **`URL`** plus optional columns such as **`airport`** (Jet2 numeric departure id or IATA).

- **Command:** `php artisan holidaysage:import-url-lists --searches=/path/to/searches.csv --hotels=/path/to/hotels.csv`
- **Defaults:** set `HOLIDAYSAGE_SEARCHES_CSV_PATH` and/or `HOLIDAYSAGE_HOTELS_CSV_PATH` to omit flags.
- **`--user=`** optional user id to own created rows; **`--dry-run`** prints URLs without saving.
- Each URL becomes one **`SavedHolidaySearch`** (`provider_import_url` set, **draft**), with criteria prefilled from the link via `ImportUrlParser`.
- **Jet2:** refresh still needs a **`/search/results`** URL per search for the smart-search API; hotel-only rows are imported for deep links and prefill — a warning is printed if any Jet2 `/beach/…` URLs were written. Replace those URLs with search-results links (or rely on seed CSV only where appropriate) until hotel-page import exists.

**Jet2 id → IATA:** extend `config/holidaysage.php` `provider_url_lists.jet2_airport_id_to_iata` (when that config exists) or set **`HOLIDAYSAGE_JET2_AIRPORT_ID_TO_IATA_JSON`** (JSON object, merged on top of defaults).

## Rollback

On the server, from `$DEPLOY_BASE/current`:

```bash
./scripts/hoist/rollback.sh
```

## Requirements summary

- PHP 8.3: intl, mbstring, xml, curl, zip, bcmath, mysql, gd, **redis**
- Node **20+** for `npm ci` / `npm run build` on the server (see [`.nvmrc`](../.nvmrc))
- Redis and MariaDB (or MySQL)
- Horizon + `schedule:work` via systemd units installed by `setup-lxc-app.sh`

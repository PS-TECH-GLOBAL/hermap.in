# Development and deployment

## Requirements

PHP 8.2+ with PDO MySQL, OpenSSL and mbstring; cURL for HTTP tests. MariaDB/MySQL, Node 20+ and npm. Apache requires mod_rewrite and permission to apply `.htaccess`. The PHP development server must use `scripts/dev-router.php` so private-file protections apply.

Keep secrets outside source control. `.env.example` documents settings without values. The local setup helper uses the database's administrative account only from the CLI; the website uses a separate HerMap account with table-scoped CRUD permissions. The helper assumes a local `root` administrative account and a database named `hermap`; it is intended for development, not an unattended production installer.

## Local installation

1. Clone the repository and run `npm ci` then `npm run build`.
2. Start your local database. Set `HERMAP_SETUP_PASSWORD` in the process environment if your local database root account has a password. Set `HERMAP_ACCESS_FILE` to a private path outside the web root for moderator access instructions. Do not pass secrets in command arguments or commit them.
3. Run `php scripts/setup-database.php`. A clean clone includes the 36-state seed and does not need another project's database exports. Country support is India-only. The helper creates a private `.env` and a generated moderator password/TOTP secret only when `.env` does not already exist. It does not rotate an existing application's credentials. Protect the generated files with account-only filesystem access; add the secret to your authenticator.
4. Set `SITE_URL=http://127.0.0.1:8095` in `.env` for the development server, or the exact Apache origin/subfolder. Keep `APP_ENV=local`.
5. Import PIN data below, then run `php -S 127.0.0.1:8095 -t . scripts/dev-router.php`. Browse `/` and `/admin`.

If an existing database needs only schema/seed checks, use `php scripts/setup-database.php --schema-only`. The included schema uses InnoDB and UTF-8. `hm_` tables hold application data; optional supplied legacy location exports are excluded from Git.

## Public location datasets

Download the historical JSON `data.json` from [dpnkrpl/indian-pincodes-database](https://github.com/dpnkrpl/indian-pincodes-database/) and the postal [GeoNames IN.zip](https://download.geonames.org/export/zip/IN.zip). Extract `IN.txt`, keeping all attribution and licence notices. These files are location directories, not report databases. Use a task-specific scratch directory outside the web root.

```sh
php scripts/import-pincodes.php /path/to/data.json /path/to/IN.txt
```

The importer normalises state aliases, adds district/city filters and combines office records with approximate PIN-centre coordinates. Entries without suitable in-India coordinates cannot submit a mapped report. A postal PIN is not a polygon or exact office entrance. The historical source is not a certification of current coverage.

The government outline and NWIC state overview files are already bundled in `assets/data`. Reproduction scripts require Python, pyproj and shapely (plus pyshp for the outline). State conversion accepts the original EPSG:7755 NWIC file and emits simplified EPSG:4326 geometry clipped to the existing SoI outline. Source/use notices are in `assets/licenses/geography.txt`.

## Validation

```sh
node tests/chart.cjs
php tests/integration.php
php tests/integration.php --http
```

Backend tests require the seeded/imported local database; HTTP tests also require the local server. Set `HERMAP_TEST_URL` if it uses another origin and `HERMAP_ACCESS_FILE` to your generated private admin instructions. Tests are refused in production, use fictional fixtures, and clean up their records/counter increments. Tests require the setup helper's generated login from your access file and the configured authenticator secret. Public UI checks should cover searchable-select keyboard navigation, dependent options, disabled countries, conditional fields, receipts, narrow layouts, map/island views, and reduced motion. Do not submit real experiences as tests.

## Before public deployment

Confirm the legal operator and grievance arrangements; review the marked policy drafts with qualified Indian counsel. Document hosting, processor arrangements, infrastructure logs, backup retention, incident response and lawful preservation. Set legal effective dates only after adoption. Source publication does not mean this work is complete.

Require HTTPS, `APP_ENV=production`, correct `SITE_URL`, protected secrets and private session/credential paths. Verify both `.htaccess` protections and direct `.php` access. Keep data directories and administrative scripts off the public server where possible. Install only runtime assets; do not serve repository metadata, tests or downloads. Confirm encryption/TOTP secrets, table-scoped database permissions and tested backups before enabling intake.

Run `php scripts/cleanup.php` from a protected maintenance scheduler so expired data is removed even during quiet periods; traffic-based cleanup uses bounded batches. The optional MapLibre picker uses local geography without paid map/geocoding APIs. It never requests device permission automatically; the separate sharing checkbox must be checked before coordinates are sent. Shared points must be inside the official outline and within 75 km of the selected approximate PIN centre. Coordinates are encrypted in the existing private context envelope, with no schema migration. Reports and encrypted optional context expire after 400 days; cumulative daily counters remain. Receipt deletion removes the active record but does not erase historical received totals. Infrastructure retention must be handled separately.

Do not weaken the minimum public threshold (five) or approval delay (48 hours by default; code enforces at least 24). Review geography updates against the official Indian outline. Never publish venue names, online handles, exact dates/times, receipts or private moderation rows through public APIs.

# HerMap.in

**Know Before You Go.**

**Every Report, Saves a Life.**

HerMap is a community-powered awareness platform for women in India. Its vision is simple: an experience shared with care can help another woman make a more informed decision about the places she visits.

Women navigate streets, public transport, workplaces and unfamiliar neighbourhoods every day. HerMap brings reported experiences together on a map so that local awareness is easier to explore. Viewing and reporting will always be free. Our humanitarian mission does not imply registered charitable status or government endorsement.

The campaign line expresses our purpose, not a guarantee that a report prevents harm. Reports are community contributions, not independently verified crime records. Missing reports do not establish safety; higher counts do not prove a place, business or person unsafe.

## What the platform does

- Explore India with state and Union Territory boundaries, names and island-group shortcuts, including Lakshadweep. Geography is served locally with source attribution.
- Search the historical PIN/post-office directory and view broad reporting-area patterns over 30, 90 or 365 days.
- Submit structured experiences without a public profile or reporting fee. Categories include workplace, online, transport and public-space incidents.
- Share optional venue details or an online account handle/platform for encrypted private review. These fields never appear on the public map.
- Optionally select a map point, enter coordinates or request device location, then separately opt in to encrypted private sharing. No paid map/geocoding API is needed; the local picker has overview geography rather than street imagery. Exact points stay off the public map.
- Keep a private receipt to check or delete a report without an account.
- Find official emergency, cybercrime, workplace-harassment and women’s support resources.
- Review reports in a password-and-TOTP protected admin panel, with moderation history and daily, weekly, monthly and all-time graphs and numbers.
- See animated community counters for active visits, total page visits since launch and reports received.

## Trust is part of the design

Reports enter a private moderation queue. Approval checks privacy and misuse; it does not verify an allegation. Public summaries use broad 0.25° geographical cells, wait at least 48 hours after approval, suppress groups below five reports, and display counts in bands of five. Category, hour and weekday patterns each require enough eligible reports.

Public totals of reports received include submissions before review and retain historical counts after deletion. Visits are page loads, including reloads and tabs; active visits are page tokens active within two minutes. These counters are not unique-person or guaranteed-human counts.

CSRF protection, a single-use arithmetic check, rate limits, a honeypot and duplicate checks reduce abuse. They cannot prove that a submission is human or truthful. Optional context uses authenticated encryption. Private report data, credentials and database dumps are excluded from this repository.

**Development status:** database-backed reporting and moderation work locally. Privacy and terms are clearly marked drafts. Public deployment still requires confirmed operator/grievance details, hosting and logging arrangements, operational safeguards and qualified Indian legal review. Publishing this code is not a production launch or a compliance certification.

## Help build a platform that reaches and helps more women

We welcome thoughtful participation from different backgrounds:

| You can contribute as… | Useful ways to help |
| --- | --- |
| Developers and security researchers | Review authentication, privacy, abuse prevention, database queries and deployment; improve accessibility, performance and tests. Disclose vulnerabilities privately. |
| Tech enthusiasts and data contributors | Test devices and languages, reproduce bugs, check geographical coverage and PIN data, and document sources and licences. |
| Designers and writers | Make reporting calmer and clearer, improve keyboard/screen-reader support, translate carefully and explain uncertainty without fear or victim-blaming. |
| Marketers and community organisers | Help women understand the platform, connect with relevant organisations and grow participation without collecting private incident stories for promotion. |
| Influencers and educators | Share accurate demonstrations, explain what counts mean, promote official support resources and encourage responsible participation. Use clearly fictional examples. |
| Domain and policy specialists | Help design moderation, grievance, survivor-protection and lawful handling processes before public launch. |

Start with an [issue](https://github.com/PS-TECH-GLOBAL/hermap.in/issues) describing a concrete improvement or a pull request with a focused change. Read [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md). Please never put reports, receipts, account allegations, credentials or survivor information in public issues, screenshots or pull requests. Contact **[help@hermap.in](mailto:help@hermap.in)** for private concerns.

## Run and contribute locally

The application uses PHP 8.2+, MariaDB/MySQL and the shared `inc` framework. Tailwind CSS, Font Awesome and MapLibre are installed locally through npm. Shared PHP includes live in `inc/`; CSS, JS and images live in their corresponding `assets/` folders.

See [the development guide](docs/DEVELOPMENT.md) for database setup, PIN import, the local server, tests and deployment requirements.

```sh
npm ci
npm run build
php scripts/setup-database.php
php -S 127.0.0.1:8095 -t . scripts/dev-router.php
```

Set `SITE_URL=http://127.0.0.1:8095` in your private `.env` before using that server. Import the public PIN datasets as described in the guide to enable mapped submissions. `/admin` requires the locally generated password and a TOTP authenticator; never commit those secrets.

## People, contact and attribution

Creator: **[Amit P. Kumar](https://x.com/iamitpkumar)**. Crafted with ❤️ by **[PS TECH GLOBAL](https://www.pstechglobal.com)**.

HerMap: [X](https://x.com/HERMAPOFFICIAL) · [Instagram](https://www.instagram.com/HERMAPOFFICIAL) · [help@hermap.in](mailto:help@hermap.in).

Third-party code and geographical data retain their own licences and conditions in [assets/licenses](assets/licenses). Survey of India outline use is subject to its non-commercial conditions. NWIC state data is generalised for display, with attribution retained. Postal coordinates are approximate and historical; no complete/current India Post coverage is claimed.

A licence for HerMap’s original code has not yet been designated by the maintainers. Do not assume an MIT or other unrestricted reuse grant. The HerMap name and supplied artwork must not be used to impersonate or imply endorsement.

[![](https://github.com/fate-srd/.github/blob/main/img/Fate-CI-style-badge.svg)](https://github.com/fate-srd)

# Fate SRD — Backend

Drupal 11 (Contenta CMS) API backend for the Fate SRD website. Local development runs on [DDEV](https://ddev.com/).

## Requirements

- [DDEV](https://docs.ddev.com/en/stable/users/install/)
- Docker
- Composer (via DDEV: `ddev composer`)

## Local setup

```bash
ddev start
ddev composer install
```

Site URL: https://fate-srd-website-backend.ddev.site

### Settings files

| File | Purpose |
|------|---------|
| `web/sites/default/settings.php` | Main settings (committed) |
| `web/sites/default/settings.ddev.php` | DDEV DB credentials (auto-generated, gitignored) |
| `web/sites/default/settings.local.php` | Local overrides (gitignored) |

## Pull the live database

Credentials live in `scripts/.env.db` (gitignored). The dump runs on the DreamHost VPS over SSH (MySQL is not open to your laptop IP).

```bash
cp scripts/.env.db.example scripts/.env.db
chmod 600 scripts/.env.db
# set SSH_HOST, SSH_USER, and DB_* values
```

Then:

```bash
./scripts/download-db.sh
# or:
ddev pull live --skip-files -y
ddev drush cr
```

This replaces the local database.

## Common commands

```bash
ddev drush cr          # rebuild caches
ddev drush status      # site status
ddev drush uli         # one-time login link
ddev ssh               # shell in the web container
ddev describe          # project URLs and services
```

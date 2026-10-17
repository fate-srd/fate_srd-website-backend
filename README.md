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
| `web/sites/default/settings.live.php` | Production database credentials (on the server only, gitignored) |

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

## Deploy

Production is a Git checkout on the DreamHost VPS, not an FTP upload.

| | Value |
|---|---|
| SSH user | `ardev_site` |
| Site directory | `/home/ardev_site/fatesrd.amazingrando.dev` |
| Domain | `fatesrd.amazingrando.dev` |

The domain’s PHP version in the DreamHost panel is 8.3. The SSH `php` command uses the same version because `~/.bashrc` puts `/usr/local/php83/bin` first on `PATH`, and `~/.bash_profile` loads `~/.bashrc`.

`web/sites/default/settings.live.php` and `web/sites/default/files` stay on the server. A pull does not replace them. Do not FTP `vendor/`, `web/core`, or contrib modules and themes over the checkout.

### One-time server setup

Install Composer for the SSH user:

```bash
mkdir -p "$HOME/bin"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir="$HOME/bin" --filename=composer
rm composer-setup.php
```

Create a read-only GitHub deploy key and add the public key under the repo’s **Settings → Deploy keys**:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519_github_fate -C "fatesrd deploy" -N ""
cat ~/.ssh/id_ed25519_github_fate.pub
```

`~/.ssh/config`:

```
Host github.com
  HostName github.com
  User git
  IdentityFile ~/.ssh/id_ed25519_github_fate
  IdentitiesOnly yes
```

Point the checkout at SSH and confirm it can fetch:

```bash
cd /home/ardev_site/fatesrd.amazingrando.dev
git remote set-url origin git@github.com:fate-srd/fate_srd-website-backend.git
ssh -T git@github.com
git fetch origin
```

### Release

On the server, once per checkout, connect `git pull` to the deploy script:

```bash
cd /home/ardev_site/fatesrd.amazingrando.dev
./scripts/install-deploy-hook.sh
```

After that, a pull installs dependencies, runs database updates, imports `web/config/sync`, and rebuilds caches:

```bash
git pull
```

The hook runs `scripts/deploy.sh`. Run that script directly to install and update without pulling. Composer is allowed to use the memory it needs (`COMPOSER_MEMORY_LIMIT=-1`). Git does not load `~/.bashrc`, so the script puts PHP 8.3 on `PATH` itself.

## Common commands

```bash
ddev drush cr          # rebuild caches
ddev drush status      # site status
ddev drush uli         # one-time login link
ddev ssh               # shell in the web container
ddev describe          # project URLs and services
```

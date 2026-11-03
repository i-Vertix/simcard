# Docker dev environment

GLPI 12 (`glpi/glpi:12.0.1`, port 8012) with this repo mounted as the plugin, plus GLPI 11
(`glpi/glpi:11.0.12`, port 8011, profile `legacy`) running simcard 1.3.1 for upgrade tests.
Database: `mariadb:11.4`. `glpi11` and `glpi12` share the DB and the `/var/glpi` volume,
so run only one of them at a time.

All commands are run from the repo root. Shorthand used below:

    dc="docker compose -f tools/docker/docker-compose.yml"

Git Bash on Windows rewrites absolute paths in arguments (`/var/glpi` becomes `C:/Program Files/Git/var/glpi`).
Prefix such commands with `MSYS_NO_PATHCONV=1` (or `export MSYS_NO_PATHCONV=1`).

## How the image behaves (from Docker Hub / glpi-project/docker-images)

- Env vars `GLPI_DB_HOST/PORT/NAME/USER/PASSWORD`: if all five are set, the DB is installed
  automatically on first start (`GLPI_SKIP_AUTOINSTALL=true` disables this).
  Schema updates on restart are automatic unless `GLPI_SKIP_AUTOUPDATE=true`; we set it so the
  11 -> 12 upgrade is done explicitly with `bin/console db:update`.
- Plugins live in `/var/www/glpi/plugins`; the repo root is mounted at `/var/www/glpi/plugins/simcard`.
- Data volume `/var/glpi` holds `config`, `files`, `logs`, `marketplace`. Container port is 80.
- Cron is disabled (`GLPI_CRONTAB_ENABLED=0`).
- Default login: user `glpi`, password `glpi` (also `tech`/`tech`, `normal`/`normal`, `post-only`/`postonly`).

## (a) Fresh GLPI 12

    $dc up -d glpi12
    $dc exec glpi12 php bin/console --version
    # http://localhost:8012
    $dc exec glpi12 php bin/console plugin:install simcard -u glpi
    $dc exec glpi12 php bin/console plugin:activate simcard

(Plugin versions before 2.0.0 declare a GLPI max version below 12 and are refused.)

## (b) Upgrade path 11 -> 12

    tools/docker/make-legacy-plugin.sh          # extracts simcard 1.3.1 (commit 456450c) to tools/docker/.legacy/simcard
    $dc --profile legacy down -v                               # start from empty volumes (optional but clean)
    $dc --profile legacy up -d glpi11
    $dc exec glpi11 php bin/console plugin:install simcard -u glpi
    $dc exec glpi11 php bin/console plugin:activate simcard
    # http://localhost:8011 : create test data (SIM cards, links to items, ...)
    $dc --profile legacy stop glpi11
    $dc up -d glpi12
    $dc exec glpi12 php bin/console db:update
    $dc exec glpi12 php bin/console plugin:resume_execution            # plugins stay suspended after a major update
    $dc exec glpi12 php bin/console plugin:install simcard -u glpi     # simcard 2.0.0
    $dc exec glpi12 php bin/console plugin:activate simcard

After `db:update`, `plugin:list` still shows simcard 1.3.1 as "Enabled" (plugins execution is suspended
by the update, so `plugin:install` refuses to run); after `plugin:resume_execution` it shows 2.0.0 as "To update".

Note: a GLPI 11 database cannot be opened by glpi11 again after `db:update` on 12; use `--profile legacy down -v` to reset.

## Logs

Inside the container, in `/var/glpi/logs/`: `php-errors.log`, `sql-errors.log`, `event.log`, `glpi.log` (created on demand), ...

    MSYS_NO_PATHCONV=1 $dc exec glpi12 tail -n 50 /var/glpi/logs/php-errors.log
    MSYS_NO_PATHCONV=1 $dc exec glpi12 grep -i simcard /var/glpi/logs/php-errors.log

## SQL

    $dc exec db mariadb -uglpi -pglpi glpi -e "SHOW TABLES LIKE 'glpi_plugin_simcard%'"
    $dc exec db mariadb -uroot -proot glpi          # interactive, as root

## Stop / reset

    $dc stop                         # keeps volumes (add --profile legacy for glpi11)
    $dc --profile legacy down -v     # removes containers AND volumes

# PflegeIndex Production Deployment Checklist

This checklist applies to the PflegeIndex v1.0 Laravel application on shared
hosting. Execute commands from the private application directory, never from
the public document root.

## 1. Define and verify paths

Before every deployment, identify these absolute paths:

- `<APP_DIR>` — private Laravel application directory;
- `<PUBLIC_DIR>` — domain document root containing only public files;
- `<DB_PATH>` — SQLite file from `DB_DATABASE`;
- `<BACKUP_DIR>` — protected backup directory outside `<PUBLIC_DIR>`.

Confirm that `<APP_DIR>`, `<DB_PATH>` and `<BACKUP_DIR>` are not reachable over
HTTP. Do not print `APP_KEY`, passwords or other environment secrets.

## 2. Pre-deployment checks

- [ ] The release commit or tag is approved and recorded.
- [ ] The working tree is clean: `git status --short` returns no output.
- [ ] PHPUnit and Pint passed for the exact release commit.
- [ ] `docs/RELEASE_VERIFICATION.md` is ready for post-deployment use.
- [ ] Impressum contains the agreed operator details and Datenschutz accurately
      describes the configured hosting and processing behavior without placeholders.
- [ ] A verified production package was built from the approved release commit;
      its Composer status and checksums are complete.
- [ ] SQLite Scenario A or B from the package manifest was selected explicitly.
- [ ] The server runs PHP 8.2 or newer with the required SQLite extensions.
- [ ] The production `.env` contains `APP_ENV=production`, `APP_DEBUG=false`
      and `APP_URL=https://pflegeindex.com`.

Record the currently deployed commit before changing anything:

```bash
cd <APP_DIR>
git rev-parse HEAD
```

Store that hash with the backup metadata.

## 3. Maintenance mode and backup

Put the current application into maintenance mode before copying SQLite so no
admin write can occur during the backup:

```bash
cd <APP_DIR>
php artisan down --retry=60
```

Create a timestamped directory under `<BACKUP_DIR>` and copy:

- the complete SQLite file from `<DB_PATH>`;
- the production `.env`;
- the recorded commit hash;
- any manually maintained files that are not part of Git.

Verify that the copied database exists, is non-empty and is readable by SQLite.
The backup directory must not be inside `public`, the domain document root or a
downloadable deployment package.

## 4. Update application code

The normal SSH/Git deployment is:

```bash
cd <APP_DIR>
git status --short
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
```

Stop if Git reports local changes, the pull is not fast-forward, Composer fails,
or a platform requirement is missing. Do not resolve conflicts directly on the
production server.

For FileZilla-only hosting, build the same release in a clean local directory
with `composer install --no-dev --optimize-autoloader`. Upload the application
core outside `<PUBLIC_DIR>` and upload only the contents intended for
`<PUBLIC_DIR>`. Never upload or overwrite:

- `.env`;
- the production SQLite database;
- backups or logs;
- `public/hot` or `fonts-manifest.dev.json`;
- `.git`, tests, local caches or `server-version` files.

## 5. Database migrations

Run migrations only after the verified backup exists:

```bash
cd <APP_DIR>
php artisan migrate --force
php artisan migrate:status
```

Do not run data imports as part of a normal code deployment unless the release
instructions explicitly require them and provide their own backup and dry-run
procedure.

## 6. Laravel caches and optimization

Clear caches created by the previous release, then rebuild the production
caches:

```bash
php artisan optimize:clear
php artisan optimize
```

`artisan optimize` builds the configuration, event, route and Blade view
caches. If granular commands are required for diagnosis, the corresponding
commands are:

```bash
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
```

Do not run both approaches unnecessarily. Never run `config:cache` until the
production `.env` has been verified.

## 7. Permissions

The PHP/web-server user needs write access only where required:

- `storage/` and its subdirectories;
- `bootstrap/cache/`;
- the SQLite file and its containing private database directory.

Application source, `.env`, backups and the database must not be world-writable.
Do not use permission mode `777`. Confirm that the web document root exposes
only the public front controller and public assets.

## 8. Bring the site online

```bash
php artisan up
```

Complete every item in `docs/RELEASE_VERIFICATION.md`. At minimum verify:

- `/up` returns HTTP 200 with the exact plain-text body `OK` (liveness only; no database or external-service check);
- home, Brandenburg, City and Facility pages render;
- `/sitemap.xml` and `/robots.txt` use the production HTTPS host;
- admin login works and protected admin routes reject guests;
- logs contain no new deployment errors.

Keep the previous backup until the release has remained stable through the
agreed observation period.

## 9. Rollback

Rollback is required when migrations, bootstrapping, primary pages, admin login
or data integrity checks fail.

1. Enable maintenance mode.
2. Record the failed release commit and preserve its logs.
3. Confirm `git status --short` is empty, then restore the exact recorded
   previous commit or immutable deployment artifact. For a Git checkout:

   ```bash
   git switch --detach <PREVIOUS_COMMIT>
   ```

   On the next normal deployment, return to the tracked release branch with
   `git switch main` before running `git pull --ff-only origin main`.
4. Run `composer install --no-dev --optimize-autoloader --no-interaction` for
   that commit.
5. If the database changed, replace it with the verified pre-deployment SQLite
   backup while the site remains in maintenance mode. Do not use
   `migrate:rollback` blindly.
6. Restore `.env` only if it was changed during the deployment.
7. Run `php artisan optimize:clear` followed by `php artisan optimize`.
8. Run the release verification checklist against the restored version.
9. Run `php artisan up` only after the rollback is verified.

Document the failure, affected time window, deployed and restored commit hashes,
database backup used and follow-up action.

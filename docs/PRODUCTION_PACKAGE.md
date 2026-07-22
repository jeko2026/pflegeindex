# PflegeIndex Production Package

This document covers only creation and inspection of the split production
package. The actual server update, backup, migrations and rollback remain in
`docs/DEPLOYMENT_CHECKLIST.md`.

## Purpose and output

Run the builder from the Laravel repository on Windows:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-production-package.ps1 -ReleaseRef <commit-or-tag>
```

The selected ref must resolve to a Git commit. The builder exports that commit
with `git archive`; it never checks out the main working directory and cannot
include its uncommitted files. Output is recreated only below the verified
repository path `build/production/`:

```text
build/production/<short-commit>/
├── private-core/
├── public-webroot/
└── manifest/
```

After a complete dependency build, three independent archives are written to
`build/production/archives/`. Private core and public webroot are never placed
in one archive.

## Local requirements

- Git with support for `git archive`;
- Windows PowerShell;
- PHP 8.2.x;
- Composer;
- network access to the configured Composer repositories when dependencies are
  not already available in Composer's cache.

`composer.json` must retain the PHP platform target `8.2.27`. The builder runs
the equivalent of:

```text
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
composer check-platform-reqs --no-dev
```

If PHP or Composer is unavailable, the builder produces an inspected partial
directory and exits with `PACKAGE BLOCKED`. It does not copy an existing
`vendor` directory and does not create ZIP archives.

Node.js and npm are not required. Active PflegeIndex layouts load the committed
files under `public/assets` directly and do not use a Vite manifest.

## Self-check

Run the non-destructive build tests with:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-production-package.ps1 -ReleaseRef <commit-or-tag> -SelfCheck
```

The self-check uses a temporary directory strictly below `build/production`
and verifies clean Git export, independence from working-tree changes,
forbidden `.env`/SQLite/log detection, required assets, `composer.lock`, the
front-controller placeholder, SHA-256 verification and repeat-build cleanup.
It never deletes outside its own temporary build directory.

## Package contents

Upload the contents of `private-core` to a new private release directory
outside the domain document root. It contains application code, configuration,
migrations, Blade views, routes, a clean writable storage skeleton, Composer
metadata and production-only `vendor`.

Upload the contents of `public-webroot` to the domain document root. It contains
only the front controller, `.htaccess`, CSS, JavaScript, Open Graph image, the
active SVG favicon and logos. The zero-byte, unused `public/favicon.ico` is
intentionally excluded.

Do not upload the `manifest` directory to webroot. Keep it locally with the
release record.

## Production front controller

`public-webroot/index.php` intentionally contains:

```text
__PRIVATE_CORE_PATH__
```

Before switching production, replace every occurrence with the absolute path
of the uploaded private-core directory. Use a Unix hosting path, not a local
Windows path. Until replacement, the front controller deliberately returns
HTTP 503. Do not modify Laravel's normal `public/index.php` for local
development.

## Production environment

Copy `manifest/.env.production.template` to `.env` inside the private core and
fill it on the server or in a protected local deployment workspace. Never put
it in public webroot or an archive.

For an update, preserve the existing production `APP_KEY`. Generating a new key
can invalidate encrypted sessions and stored encrypted data. Replace
`__ABSOLUTE_EXTERNAL_SQLITE_PATH__` with an absolute SQLite path outside both
the release directory and public webroot.

The template intentionally uses database-backed sessions and cache, the
`stack` channel with daily Laravel logs retained for 30 days, synchronous
queues and the `log` mail transport. Change these only when the corresponding
production service has been explicitly configured.

## SQLite and GeoCore decision

SQLite is never included in the application package. Read
`manifest/DATABASE_DEPLOYMENT_DECISION.md` and select one scenario:

- Scenario A: a new installation receives a separately reviewed and checksummed
  database payload with all expected directory and GeoCore data;
- Scenario B: an existing production database is preserved, backed up and
  migrated, while GeoCore is handled by a separate approved data-fix.

Do not run `geocore:import-brandenburg` in production. The command is designed
to reject that environment. Do not replace a production database with the local
development database.

## Manifest verification

Review `manifest/RELEASE_MANIFEST.md` and `manifest/build-info.json`. Verify all
payload files against `manifest/files.sha256`. A valid build reports a passed
forbidden-file scan and a complete Composer dependency build.

The public archive must contain `.htaccess` and `index.php` directly at archive
root. The private archive must contain `artisan`, `app`, `bootstrap` and
`vendor` directly at archive root. An additional wrapping directory indicates
an invalid upload layout.

## Checks before upload

1. Confirm the manifest commit is the approved release commit.
2. Confirm the main working directory still contains all intended local work.
3. Confirm Composer status is `complete` and checksums verify.
4. Confirm all three ZIP archives were created and validated.
5. Confirm `index.php` still has no local path, then replace its placeholder in
   a protected deployment copy.
6. Prepare production `.env` without changing the existing `APP_KEY`.
7. Confirm the external SQLite path and select database Scenario A or B.
8. Inspect both ZIP contents before FileZilla upload.
9. Follow the backup and maintenance procedure in the deployment checklist.
10. Configure nginx/control-panel redirects from
    `deployment/nginx-canonical-redirect.conf`; nginx does not process the
    packaged Apache `.htaccess` file.

## Cleanup

The current implementation uses `git archive`, so it does not leave a Git
worktree to remove. Temporary export directories are removed automatically only
after their paths are confirmed to be children of `build/production`.

To remove build output manually, first resolve and inspect the exact directory:

```powershell
Resolve-Path build/production/<short-commit>
```

Delete only that confirmed child directory. Never run recursive deletion
against the repository root, a home directory, an unresolved variable or a
wildcard target.

## Never upload

- `.env`, credentials, keys or tokens;
- SQLite, WAL/SHM files, database backups or data exports;
- logs, sessions, compiled views or runtime cache content;
- `.git`, tests, PHPUnit cache, IDE files or local reports;
- `node_modules`, Vite hot/manifest files or source-only frontend scaffolding;
- dev Composer dependencies, `vendor-old`, `server-version` or old ZIP files;
- the manifest directory inside public webroot;
- a front controller containing a local Windows path.

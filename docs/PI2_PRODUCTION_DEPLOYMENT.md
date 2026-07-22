# PI-2 Production Deployment Preparation

## 1. Status and scope

**Preparation date:** 2026-07-22

**Branch:** `pi-2`

**Application release commit:** `8bc17b22188f511ea7f102319afdd671f7b9bc51`

**Release tag:** `v1.0.0-pi1`

**Deployment status:** **CONDITIONAL — DO NOT DEPLOY until every blocking gate in
this document is confirmed**

This document prepares the completed PI-1 application for a controlled manual
deployment on the existing shared hosting. It does not record a production
deployment and does not authorize one by itself.

The application payload is built from the exact PI-1 closure commit above. The
documentation-only commit that adds this file does not change the runtime payload
and is reported separately in the sprint result. Never deploy an unrecorded branch
tip: the package manifest must name the exact application release commit.

No production server, production database, DNS, nginx configuration or live `.env`
was changed during this preparation.

---

## 2. Git release baseline

The following facts were refreshed from `origin` before this document was prepared:

| Check | Result |
|---|---|
| Current branch | `pi-2` |
| Initial working tree | Clean |
| `pi-2` creation point | `8bc17b22188f511ea7f102319afdd671f7b9bc51` |
| Local `main` | `8bc17b22188f511ea7f102319afdd671f7b9bc51` |
| `origin/main` after `git fetch origin` | `8bc17b22188f511ea7f102319afdd671f7b9bc51` |
| Tag `v1.0.0-pi1` peeled commit | `8bc17b22188f511ea7f102319afdd671f7b9bc51` |
| `origin/pi-2` | Does not exist; local `pi-2` has no upstream |

The `pi-2` branch was therefore created from the approved PI-1 closure, and local
`main` was synchronized with the refreshed `origin/main` reference.

---

## 3. Readiness decision

### 3.1 Repository result

**READY.** No application-code blocker was found. PHP compatibility, Composer lock,
routes, configuration caching, tests, SEO behavior, assets, technical legal-page
structure and the production package builder passed the available local checks.

No runtime code, routes, database schema or public URLs required correction in this
sprint.

### 3.2 Deployment result

**CONDITIONAL / NO-GO until the following production facts are confirmed:**

1. the complete operator details used in Impressum are confirmed by the owner;
2. hosting provider, server location, AV-Vertrag status, provider logs and retention
   are confirmed;
3. mailbox provider, processing location, forwarding and retention are confirmed;
4. the active production SQLite file is identified and moved or retained at a stable
   private path outside both release directories and the public webroot;
5. a consistent SQLite backup and a usable restore procedure are verified;
6. real `<APP_DIR>`, `<PUBLIC_DIR>`, `<DB_PATH>` and `<BACKUP_DIR>` paths and PHP-user
   permissions are confirmed privately;
7. production uses `LOG_STACK=daily` and `LOG_DAILY_DAYS=30`;
8. nginx one-hop redirects and static cache rules are applied and validated;
9. `GA4_MEASUREMENT_ID` and `CLARITY_PROJECT_ID` are empty in the effective cached
   configuration;
10. provider-managed `webstat/`, public backup pages and other unmanaged webroot files
    are inventoried and explicitly retained, protected or removed by the operator;
11. SQLite deployment Scenario A or B is selected;
12. the owner gives explicit deployment approval after reviewing these facts.

These are operational, data-placement and approval blockers. They cannot be repaired
reliably by changing Laravel code.

### 3.3 Available hosting snapshot warning

A previously downloaded, sanitized server snapshot was inspected only as supporting
evidence; it is not proof of the current live state. In that snapshot:

- `DB_DATABASE` was absent, so Laravel would fall back to
  `database/database.sqlite` inside the current private release;
- a database existed inside that old release, while a different older database also
  existed elsewhere and was not referenced;
- logging still used `LOG_STACK=single` without a 30-day daily-retention setting;
- GA4 and Clarity IDs were absent and therefore disabled;
- the webroot contained an existing Google verification file, `webstat/`,
  `index-hosting-backup.html` and an unused zero-byte `favicon.ico`.

Do not assume that the separately located database is active. Confirm the effective
live path before copying or switching anything. If the active database is still
inside the old release, deployment is blocked until it has a consistent verified
backup and a stable external `<DB_PATH>`.

`webstat/` is evidence of possible hosting-layer log analytics, not GA4 or Clarity.
Its access, data fields and retention require provider confirmation. Do not silently
delete provider-managed files during upload.

---

## 4. Verified production package

The older `fd01a71f` archives are obsolete and must not be deployed. They predate the
Trust Layer, Content Layer, analytics configuration and current production assets.

A fresh split package was built from the exact application commit
`8bc17b22188f511ea7f102319afdd671f7b9bc51`.

### 4.1 Build result

| Item | Verified result |
|---|---|
| PHP target/build runtime | 8.2.27 |
| Laravel version | 12.64.0 |
| Composer production install | Complete |
| Composer platform check | Complete |
| Production-only `vendor` | Present |
| Dev dependencies | Excluded |
| Forbidden-file scan | Passed |
| SQLite included | No |
| Front-controller private path | Placeholder intentionally retained |
| Package checksums | 6,316 verified, 0 failures |
| Archives | 3 created |

### 4.2 Local archive inventory

The archives are local ignored build outputs, not Git content:

| Archive | Bytes | SHA-256 |
|---|---:|---|
| `pflegeindex-private-core-8bc17b22.zip` | 7,789,706 | `B01A2C14CE641E849DFA36585089C48C4C8A990FB8713C5547E4227063D919CE` |
| `pflegeindex-public-webroot-8bc17b22.zip` | 638,933 | `80A069C5A96BB37F9E4B3F1B6DA0BFB722A0F6A0D055597AA8CB65BEF5295CBA` |
| `pflegeindex-manifest-8bc17b22.zip` | 303,139 | `ADDA3EE631B6F3A312EDE57C56FA6170AAF6AC21994E6D376F955768464182DE` |

Before upload, recalculate these hashes on the actual files. Do not use an archive
whose name, size or hash differs.

### 4.3 Package layout

```text
private-core/
├── app/
├── bootstrap/
├── config/
├── database/migrations/
├── resources/views/
├── routes/
├── storage/                 clean writable skeleton
├── vendor/                  production dependencies only
├── artisan
├── composer.json
└── composer.lock

public-webroot/
├── .htaccess
├── index.php                contains __PRIVATE_CORE_PATH__ until prepared
├── assets/
├── favicon.svg
├── logo.svg
└── logo-light.svg

manifest/
├── .env.production.template
├── DATABASE_DEPLOYMENT_DECISION.md
├── RELEASE_MANIFEST.md
├── build-info.json
└── files.sha256
```

The manifest stays local or in a protected release archive. It must never be copied
into the public webroot.

---

## 5. Runtime requirements

### 5.1 PHP

- PHP **8.2.27** is the certified target;
- SAPI may differ between CLI and the web server, so check both;
- PHP must be allowed to read the private core and write only to the approved runtime
  locations;
- `proc_open` or shell access is not required for public requests.

### 5.2 Required PHP extensions

Confirm on the target host before upload:

- `ctype`;
- `curl`;
- `dom`;
- `fileinfo`;
- `filter`;
- `hash`;
- `iconv`;
- `json`;
- `libxml`;
- `mbstring`;
- `openssl`;
- `PDO`;
- `pdo_sqlite`;
- `session`;
- `sqlite3`;
- `tokenizer`;
- `xml`, `xmlreader`, `xmlwriter`;
- `zip`;
- `zlib`.

`composer check-platform-reqs --no-dev` does not independently require every SQLite
extension because they are not declared as Composer packages. Check `pdo_sqlite` and
`sqlite3` explicitly with `php -m` or the protected server diagnostics.

### 5.3 Composer

Preferred FileZilla workflow: upload the verified production-only `vendor` from the
private-core archive. Server Composer is then optional.

If Composer is available on the server, use the lock file only:

```bash
cd <NEW_APP_DIR>
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
composer check-platform-reqs --no-dev
```

Never run `composer update` on production. Never upload the development `vendor`.
If neither a verified packaged `vendor` nor server Composer is available, stop.

---

## 6. Required private topology

Determine these paths from the hosting panel or provider. Do not substitute guessed
paths:

| Placeholder | Meaning | Required location |
|---|---|---|
| `<CURRENT_APP_DIR>` | Currently active private Laravel core | Outside `<PUBLIC_DIR>` |
| `<NEW_APP_DIR>` | New uniquely named private Laravel core | Outside `<PUBLIC_DIR>` and different from current release |
| `<PUBLIC_DIR>` | Domain document root | Contains public front controller/assets only |
| `<CURRENT_DB_PATH>` | Confirmed currently active SQLite file | Must be established from effective live config |
| `<DB_PATH>` | Stable production SQLite path | Outside old/new releases and `<PUBLIC_DIR>` |
| `<BACKUP_DIR>` | Protected backup location | Outside `<PUBLIC_DIR>` and release directories |
| `<PREVIOUS_COMMIT>` | Previously deployed release identifier | Record before changes |
| `<RELEASE_COMMIT>` | Intended application commit | Must equal `8bc17b22188f511ea7f102319afdd671f7b9bc51` |

The packaged `index.php` deliberately returns HTTP 503 while
`__PRIVATE_CORE_PATH__` remains. Replace the placeholder only in a protected
deployment copy with the exact Unix `<NEW_APP_DIR>` path. Do not write a local Windows
path into the front controller.

---

## 7. Production environment variables

Create or merge the real `.env` inside `<NEW_APP_DIR>`. Never put it in
`<PUBLIC_DIR>`, Git, a ZIP sent through an untrusted channel or this document.

### 7.1 Required application values

| Key | Required production rule |
|---|---|
| `APP_NAME` | `PflegeIndex` |
| `APP_ENV` | `production` |
| `APP_KEY` | Preserve the existing production key during an update; never print it |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://pflegeindex.com` |
| `APP_LOCALE` | `de` |
| `APP_FALLBACK_LOCALE` | `de` |
| `APP_FAKER_LOCALE` | `de_DE` |
| `APP_TIMEZONE` | `Europe/Berlin` |
| `APP_MAINTENANCE_DRIVER` | `file` |
| `BCRYPT_ROUNDS` | `12` |

### 7.2 Logging

| Key | Required value |
|---|---|
| `LOG_CHANNEL` | `stack` |
| `LOG_STACK` | `daily` |
| `LOG_DAILY_DAYS` | `30` |
| `LOG_DEPRECATIONS_CHANNEL` | `null` |
| `LOG_LEVEL` | `warning` |

Laravel logs must remain private under `<NEW_APP_DIR>/storage/logs`. Provider access
and error logs are separate and require provider confirmation.

### 7.3 Database and runtime stores

| Key | Required value/rule |
|---|---|
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | Exact absolute Unix `<DB_PATH>`; never leave implicit during release-directory deployment |
| `DB_FOREIGN_KEYS` | `true` |
| `SESSION_DRIVER` | `database` |
| `SESSION_LIFETIME` | `120` |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_PATH` | `/` |
| `SESSION_DOMAIN` | `null` unless a reviewed requirement changes it |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_HTTP_ONLY` | `true` |
| `SESSION_SAME_SITE` | `lax` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` |
| `BROADCAST_CONNECTION` | `log` |
| `FILESYSTEM_DISK` | `local` |

Database-backed sessions and cache make SQLite write access operationally required
for admin login and cache operations.

### 7.4 Mail

| Key | Required current value/rule |
|---|---|
| `MAIL_MAILER` | `log`; current public contact uses `mailto:` rather than application mail |
| `MAIL_FROM_ADDRESS` | `info@pflegeindex.com` |
| `MAIL_FROM_NAME` | `PflegeIndex` or `${APP_NAME}` |

Do not configure SMTP until its provider, privacy, credentials and retention are
approved.

### 7.5 Analytics — mandatory disabled state

There is no Consent Layer. The layout loads GA4 or Clarity immediately when a nonblank
ID is configured. Therefore the effective production values must be exactly empty:

```dotenv
GA4_MEASUREMENT_ID=
CLARITY_PROJECT_ID=
```

The current production templates omit these two keys; omission resolves to disabled
for a fresh environment, but the real production `.env` and server environment may
retain old values. Add the empty keys explicitly, clear old cached configuration and
verify the rendered HTML and network behavior. A nonblank value is a deployment
blocker until Consent Layer exists.

Never print the effective IDs while checking them. Verify only that both are blank
and that analytics hostnames are absent from public HTML.

---

## 8. Filesystem and permissions

The PHP/web-server user must have:

- read access to `<NEW_APP_DIR>` source and `vendor`;
- write access to `<NEW_APP_DIR>/storage` and all required subdirectories;
- write access to `<NEW_APP_DIR>/bootstrap/cache`;
- read/write access to `<DB_PATH>`;
- create/write access in the private directory containing `<DB_PATH>` for SQLite
  sidecar files;
- read access to the real private `.env`.

The PHP user does not need general write access to source files, `vendor`,
`<PUBLIC_DIR>`, `.env` or backups during normal requests. Do not use mode `777`.
Shared-hosting ownership and ACLs vary, so verify using the actual PHP user instead of
assuming a numeric mode is sufficient.

Confirm that these locations are not reachable through HTTP:

- `<CURRENT_APP_DIR>`;
- `<NEW_APP_DIR>`;
- `<DB_PATH>` and its directory;
- `<BACKUP_DIR>`;
- `.env`;
- `storage` and logs;
- the release manifest.

---

## 9. Never overwrite or upload blindly

### 9.1 Private data and state

Never overwrite with package content:

- the current production `.env` or its `APP_KEY`;
- the active SQLite file;
- SQLite `-wal`, `-shm` or `-journal` sidecars;
- database backups;
- current production logs;
- user/session/runtime data from the active release;
- provider TLS keys or certificates;
- hosting control-panel configuration.

Use a new `<NEW_APP_DIR>` instead of copying application files over
`<CURRENT_APP_DIR>`.

### 9.2 Public webroot

Back up and inventory the webroot before replacing package-managed files. The package
manages exactly:

- `.htaccess`;
- `index.php`;
- `assets/admin.css`;
- `assets/app.js`;
- `assets/og-image.png`;
- `assets/styles.css`;
- `favicon.svg`;
- `logo.svg`;
- `logo-light.svg`.

Preserve or explicitly decide the fate of unmanaged files. The available snapshot
contains:

- a Google Search Console verification HTML file — preserve it while ownership uses
  this method;
- `webstat/` — do not expose, remove or retain it without provider/operator review;
- `index-hosting-backup.html` — if publicly accessible, move it outside webroot after
  backup and approval;
- a zero-byte `favicon.ico` — not used by the active layout, but do not treat it as a
  package file.

Do not delete the whole webroot and re-upload only nine package files.

---

## 10. SQLite safety procedure

### 10.1 Select the scenario

Choose one before deployment:

- **Scenario A — new production installation:** transfer a separately reviewed,
  checksummed production data payload outside the release and public webroot;
- **Scenario B — existing production update:** preserve the active production
  database, make a consistent verified backup and run only reviewed migrations.

Do not infer the scenario from local files. Do not upload the local development
SQLite as part of the application package.

### 10.2 Identify the active database

Privately determine the effective `DB_DATABASE` on the live application without
printing unrelated `.env` values.

If `DB_DATABASE` is absent, Laravel defaults to:

```text
<CURRENT_APP_DIR>/database/database.sqlite
```

Confirm that file using application behavior, size, modification date, integrity,
schema and expected production counts. Do not select another similarly named database
because it looks newer.

### 10.3 Enter a consistent maintenance state

Preferred: use provider-level maintenance that prevents all application and admin
writes during database backup and front-controller switch.

If only Laravel maintenance is available, protect both releases:

```bash
cd <CURRENT_APP_DIR>
php artisan down --retry=60

cd <NEW_APP_DIR>
php artisan down --retry=60
```

The old release maintenance marker lives in old `storage`. It does not protect the new
core after `index.php` switches to `<NEW_APP_DIR>`.

### 10.4 Check sidecars and journal mode

Before copying, inspect:

```text
<CURRENT_DB_PATH>
<CURRENT_DB_PATH>-wal
<CURRENT_DB_PATH>-shm
<CURRENT_DB_PATH>-journal
```

If a non-empty WAL or journal exists, do not copy the main file alone. Use the SQLite
backup API/CLI or a provider-consistent filesystem snapshot while writes are stopped.

With a trusted `sqlite3` CLI, a controlled example is:

```bash
sqlite3 "<CURRENT_DB_PATH>" ".backup '<BACKUP_DB_PATH>'"
sqlite3 "<BACKUP_DB_PATH>" "PRAGMA integrity_check;"
sha256sum "<BACKUP_DB_PATH>"
```

Expected integrity output is exactly `ok`. If `sqlite3` or a provider snapshot is not
available, stop and establish a supported backup method; do not improvise with a live
single-file copy.

### 10.5 Externalize the database when required

If the active DB is inside `<CURRENT_APP_DIR>`:

1. keep maintenance active;
2. create and verify the backup;
3. copy or restore the consistent database to stable `<DB_PATH>`;
4. verify SHA-256, integrity, schema and production row counts;
5. set the new private `.env` to the exact absolute `<DB_PATH>`;
6. verify the PHP user can create SQLite sidecars in the parent directory;
7. do not remove the old database until the observation period and restore test have
   completed.

### 10.6 Migration rule

Only after backup verification:

```bash
cd <NEW_APP_DIR>
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
```

Do not run seeders, normal imports, GeoCore imports, approved-mapping commands or
`migrate:fresh`. Do not use `migrate:rollback` as an automatic recovery strategy.

---

## 11. Manual deployment sequence

Every step lists where it runs, expected evidence and safe response to failure.

### Step 1 — Confirm the release locally

**Where:** local clean repository.

```powershell
git fetch origin
git switch pi-2
git status --short
git rev-list -n 1 v1.0.0-pi1
```

**Expected:** no working-tree output; release resolves to
`8bc17b22188f511ea7f102319afdd671f7b9bc51`.

**Verify:** compare with this document and the package manifest.

**Failure/rollback:** stop; do not build or upload an unapproved ref.

### Step 2 — Build or re-verify the split package

**Where:** local clean checkout with PHP 8.2 and Composer available.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/build-production-package.ps1 -ReleaseRef v1.0.0-pi1
```

**Expected:** `PACKAGE READY WITH WARNINGS`, three archives, production dependency
status `complete`, forbidden scan `passed`, checksums verified.

**Verify:** review `RELEASE_MANIFEST.md`, `build-info.json`, `files.sha256`, archive
root layout and the three SHA-256 values in section 4.

**Failure/rollback:** discard only the incomplete build child directory; keep existing
production untouched. Never use a partial archive.

The builder currently exports application source from the requested ref but copies
four deployment resources from the clean working tree. For this release, build only
from a clean checkout where `deployment/index.production.php`,
`deployment/public.production.htaccess`, `deployment/.env.production.template` and
`deployment/DATABASE_DEPLOYMENT_DECISION.md` equal the selected commit.

### Step 3 — Record the existing production state

**Where:** hosting panel or SSH in `<CURRENT_APP_DIR>`.

```bash
cd <CURRENT_APP_DIR>
git rev-parse HEAD 2>/dev/null || true
php -v
php -m
php artisan about --only=environment
php artisan migrate:status
```

**Expected:** current release identifier recorded; PHP 8.2.27; required extensions;
production environment and debug OFF.

**Verify:** save evidence privately without secrets. If Git is not deployed, use the
previous release manifest or recorded release ID.

**Failure/rollback:** stop before upload. Unknown current state makes rollback unsafe.

### Step 4 — Confirm paths, disk and permissions

**Where:** hosting panel/SSH.

Resolve `<CURRENT_APP_DIR>`, `<NEW_APP_DIR>`, `<PUBLIC_DIR>`, `<CURRENT_DB_PATH>`,
`<DB_PATH>` and `<BACKUP_DIR>`. Confirm free disk for the old core, new core, backup,
database sidecars and caches.

**Expected:** all private paths are outside webroot; PHP can write only required
locations.

**Verify:** use the protected diagnostics or provider tools; verify HTTP cannot reach
private files.

**Failure/rollback:** stop. Do not compensate with `777`.

### Step 5 — Upload the new private core

**Where:** FileZilla to `<NEW_APP_DIR>`.

Upload the extracted contents of `private-core/` directly into an empty uniquely named
directory. Do not upload the wrapping folder unless `<NEW_APP_DIR>` is meant to be
that folder.

**Expected:** `<NEW_APP_DIR>/artisan`, `vendor/autoload.php`, `bootstrap/app.php`,
routes and storage skeleton exist.

**Verify:** compare selected file sizes/checksums and confirm no `.env` or SQLite was
included.

**Failure/rollback:** leave current front controller unchanged; remove or replace only
the known incomplete new directory after verifying its exact path.

### Step 6 — Prepare the protected new environment

**Where:** hosting panel/private `<NEW_APP_DIR>`.

Start from the current production `.env` or safe template, preserve `APP_KEY`, set the
values in section 7 and explicitly keep both analytics IDs empty.

**Expected:** canonical URL, debug off, stable external DB path, daily logs, secure
admin session and no analytics IDs.

**Verify:** inspect only required keys privately. Do not paste the file into logs,
tickets or the terminal transcript.

**Failure/rollback:** stop; current application remains active.

### Step 7 — Enter maintenance

**Where:** provider maintenance control, or both current and new cores.

```bash
cd <CURRENT_APP_DIR>
php artisan down --retry=60
cd <NEW_APP_DIR>
php artisan down --retry=60
```

**Expected:** writes are blocked across the switch window.

**Verify:** an unauthenticated request receives the approved maintenance response;
provider-level maintenance is preferred.

**Failure/rollback:** do not copy SQLite or switch public files.

### Step 8 — Create the full backup set

**Where:** private `<BACKUP_DIR>`.

Back up and record:

- consistent SQLite snapshot and sidecar state;
- current `.env`;
- `<PREVIOUS_COMMIT>` or release manifest;
- all nine package-managed public files;
- complete unmanaged webroot inventory;
- relevant nginx/control-panel configuration;
- checksums, timestamps and the person performing the backup.

**Expected:** protected non-empty backup with integrity `ok` and recoverable public
assets/configuration.

**Verify:** read/restore the backup in a safe location; do not rely only on archive
creation success.

**Failure/rollback:** leave maintenance active until current state is known safe, then
bring the unchanged old release back online. Do not continue.

### Step 9 — Stabilize the SQLite path

**Where:** private server paths.

Follow section 10. If the live database is already at a stable external path, preserve
it. If it is inside the old core, create/verify the stable `<DB_PATH>` and update only
the new private `.env`.

**Expected:** new core and future releases reference the same private data file.

**Verify:** hash, integrity, schema, counts and PHP-user write/sidecar access.

**Failure/rollback:** keep the old core and original DB untouched; restore from the
verified snapshot only if a copy operation changed the active DB.

### Step 10 — Verify dependencies in the new core

**Where:** `<NEW_APP_DIR>`.

With packaged vendor:

```bash
cd <NEW_APP_DIR>
php -r "require 'vendor/autoload.php'; echo 'autoload ok', PHP_EOL;"
```

If server Composer is available, additionally run:

```bash
composer check-platform-reqs --no-dev
```

**Expected:** autoload succeeds and all production platform requirements pass.

**Verify:** `vendor/autoload.php` exists; no PHPUnit/dev-package directories exist.

**Failure/rollback:** do not run migrations or switch the front controller.

### Step 11 — Run reviewed database and cache commands

**Where:** `<NEW_APP_DIR>`, maintenance still active, backup verified.

```bash
cd <NEW_APP_DIR>
php artisan optimize:clear
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
php artisan optimize
php artisan route:list
```

**Expected:** migrations complete, configuration/routes/views cache successfully and
33 application routes remain available for this release.

**Verify:** `php artisan about --only=environment` reports production, debug OFF,
canonical URL, PHP 8.2.27 and locale `de`. Verify analytics absence without printing
IDs.

**Failure/rollback:** keep maintenance active, preserve logs and restore the database
backup if schema/data changed. Do not use blind migration rollback.

### Step 12 — Prepare the production front controller

**Where:** protected local copy or private server staging area.

Replace every `__PRIVATE_CORE_PATH__` occurrence in packaged `index.php` with the exact
Unix `<NEW_APP_DIR>`. Do not edit Laravel's normal local `public/index.php`.

**Expected:** autoload and bootstrap references resolve under `<NEW_APP_DIR>`; no
placeholder or Windows path remains.

**Verify:** inspect only this file and, if possible, syntax-check it with `php -l`.

**Failure/rollback:** keep the current production `index.php`; do not upload the
unconfigured controller, which intentionally returns 503.

### Step 13 — Upload public assets and switch last

**Where:** FileZilla to `<PUBLIC_DIR>`, maintenance active.

1. Preserve unmanaged files according to section 9.
2. Upload the eight package-managed non-index files.
3. Verify sizes/checksums.
4. Upload the prepared new `index.php` last.

**Expected:** public assets and front controller belong to the same release.

**Verify:** no wrapping `public-webroot/` folder was introduced; manifest and private
files are absent from webroot.

**Failure/rollback:** restore all package-managed public files from backup, especially
`index.php`, CSS, JS and OG image; the old core remains available.

### Step 14 — Apply hosting-server rules

**Where:** hosting control panel/provider-managed nginx, not Laravel.

Apply equivalents of:

- `deployment/nginx-canonical-redirect.conf`;
- `deployment/nginx-static-assets.conf`.

Preserve provider TLS certificate directives. The packaged `.htaccess` is useful for
Apache but does not configure nginx.

**Expected:** all HTTP/`www` variants redirect once to HTTPS non-www; CSS/JS and
SVG/PNG receive the intended cache policy.

**Verify:** validate/reload through the provider and run the header tests in section
12.

**Failure/rollback:** restore the exported previous nginx configuration before taking
the site out of maintenance.

### Step 15 — Bring the new release online

**Where:** `<NEW_APP_DIR>` or provider maintenance control.

```bash
cd <NEW_APP_DIR>
php artisan up
```

Disable provider maintenance only after the new core is ready.

**Expected:** canonical homepage responds from the new release.

**Verify:** complete every smoke check in section 12 immediately.

**Failure/rollback:** re-enable provider maintenance and execute section 13.

### Step 16 — Observe and retain rollback material

**Where:** hosting logs, monitoring and release record.

Keep `<CURRENT_APP_DIR>`, the verified backup, previous managed webroot files and
nginx export through the agreed observation period.

**Expected:** no new critical errors, database locks, missing assets or redirect
chains.

**Verify:** review Laravel daily logs, provider error logs, disk usage and critical
routes.

**Failure/rollback:** follow section 13 while evidence is intact.

---

## 12. Mandatory post-deployment verification

Record timestamp, HTTP status, final URL, relevant headers and the deployed release
commit. Do not mark a check complete from visual memory.

### 12.1 Public application

- [ ] `https://pflegeindex.com/` returns HTTP 200.
- [ ] `https://pflegeindex.com/brandenburg.html` returns HTTP 200.
- [ ] `https://pflegeindex.com/brandenburg/potsdam.html` returns HTTP 200.
- [ ] `https://pflegeindex.com/brandenburg/landkreis/potsdam.html` returns HTTP 200.
- [ ] `https://pflegeindex.com/pflegeeinrichtungen/brandenburg/potsdam/3w-ambulante-pflege-intensiv-14467` returns HTTP 200.
- [ ] `/pflegeheime.html?q=Pflege` returns search results and contains
  `noindex,follow`.
- [ ] `/pflegeheime.html?page=2` returns HTTP 200 with page-specific self-canonical,
  title and description.
- [ ] malformed and out-of-range pagination returns the expected 404.
- [ ] mobile quick actions appear below the facility heading when contact data exists.
- [ ] `PflegeIndex Qualität` Trust Layer appears and does not claim to rate the
  facility.
- [ ] `Was Sie wissen sollten`, FAQ and related facilities Content Layer appear.

### 12.2 SEO and indexing controls

- [ ] `/sitemap.xml` returns HTTP 200, valid XML and HTTPS non-www URLs.
- [ ] `/robots.txt` returns HTTP 200, plain text and points to the production sitemap.
- [ ] canonical URLs use only `https://pflegeindex.com`.
- [ ] no public page emits duplicate canonical elements.
- [ ] `/up` returns HTTP 200, exact body `OK`, no session cookie,
  `Cache-Control: no-store...` and
  `X-Robots-Tag: noindex, nofollow, noarchive`.
- [ ] admin responses include `X-Robots-Tag: noindex, nofollow, noarchive`.
- [ ] Impressum and Datenschutz remain excluded from sitemap and contain
  `noindex,nofollow` meta.

### 12.3 Redirects

Without following redirects, verify each request keeps the path/query and returns one
direct `301` to the exact canonical URL, without `:443`:

```bash
curl -sS -o /dev/null -D - "http://pflegeindex.com/brandenburg.html?check=1"
curl -sS -o /dev/null -D - "http://www.pflegeindex.com/brandenburg.html?check=1"
curl -sS -o /dev/null -D - "https://www.pflegeindex.com/brandenburg.html?check=1"
```

- [ ] HTTP apex → HTTPS non-www in one redirect.
- [ ] HTTP www → HTTPS non-www in one redirect.
- [ ] HTTPS www → HTTPS non-www in one redirect.
- [ ] HTTPS non-www returns content without a canonical redirect.
- [ ] unknown hostnames are not mapped to the application webroot, or the provider
  default vhost rejects them.

### 12.4 Assets and cache

- [ ] `/assets/og-image.png` returns HTTP 200 directly, no redirect.
- [ ] OG image `Content-Type` is `image/png` and `Content-Length` is non-zero.
- [ ] OG image is 1254 × 1254 and public pages use the absolute HTTPS URL.
- [ ] `/assets/styles.css?v=20260722-1` loads as CSS.
- [ ] `/assets/app.js?v=20260722-1` loads as JavaScript.
- [ ] CSS/JS headers contain the approved one-year immutable cache policy.
- [ ] SVG/PNG headers contain the approved 30-day cache policy.
- [ ] footer logo has stable dimensions and no visible layout shift.

### 12.5 Legal and analytics technical checks

- [ ] `/impressum.html` returns HTTP 200 with confirmed operator data.
- [ ] `/datenschutz.html` returns HTTP 200 and reflects confirmed hosting, logging,
  mailbox and retention facts.
- [ ] no known placeholder or unconfirmed-production warning remains after the owner
  has supplied and approved the facts.
- [ ] public HTML contains no `googletagmanager.com`, `gtag(`, `clarity.ms` or Clarity
  bootstrap code.
- [ ] browser network shows no GA4, Clarity, analytics cookie or tracking request.
- [ ] no hosting panel injects unreviewed analytics, CDN, WAF or tracking code.

### 12.6 Operations

- [ ] effective `APP_ENV=production`, `APP_DEBUG=false` and canonical `APP_URL` are
  confirmed without printing secrets.
- [ ] effective `DB_DATABASE` is the stable external `<DB_PATH>`.
- [ ] storage, bootstrap cache, DB and DB parent are writable by PHP; private source
  and `.env` are not world-writable.
- [ ] database `PRAGMA integrity_check` returns `ok` and foreign-key check is empty.
- [ ] admin login works over HTTPS; guests cannot access protected admin pages.
- [ ] daily Laravel log is created and retention is configured for 30 days.
- [ ] Laravel and provider logs contain no new critical deployment errors.
- [ ] disk space remains above the agreed threshold after caches/log creation.
- [ ] backup, previous commit, archive hashes and deployed commit are recorded.
- [ ] Google verification file remains available if still used.
- [ ] `webstat/` and `index-hosting-backup.html` have an explicit protected/removed
  decision and are not unintentionally public.

Any failed database, security, redirect, primary-page, asset or legal-technical check
requires maintenance and rollback. Do not approve a partially checked release.

---

## 13. Rollback procedure

### 13.1 Trigger conditions

Rollback immediately if:

- migrations fail or data counts/integrity change unexpectedly;
- the new front controller returns 500/503 after configuration;
- primary public pages, admin login, CSS, JavaScript or OG image fail;
- redirects loop, chain or expose a noncanonical host;
- the new release writes to the wrong SQLite file;
- analytics loads while Consent Layer is absent;
- critical log errors continue after one controlled retry.

### 13.2 Controlled rollback

1. Enable provider-level maintenance or protect both cores.
2. Record the failed release, time, symptoms and relevant private logs.
3. Restore the previous nginx/control-panel configuration if changed.
4. Restore all nine package-managed public files, uploading the previous
   `index.php` last.
5. Confirm the restored index points to `<CURRENT_APP_DIR>`.
6. If migrations or a database move changed state, restore the verified SQLite backup
   consistently while writes remain stopped. Remove/replace sidecars only as part of
   the approved SQLite restore method.
7. Restore the previous `.env` only if it was changed; preserve its original
   `APP_KEY`.
8. In `<CURRENT_APP_DIR>`, run:

   ```bash
   php artisan optimize:clear
   php artisan optimize
   ```

9. Verify old primary pages, admin, database integrity, assets and redirects.
10. Bring only the restored old release online.
11. Keep the failed release and evidence private until the incident is understood.

Do not use `git reset --hard`, an unreviewed `migrate:rollback` or a copied local
database as a shortcut.

---

## 14. Known limitations and accepted boundaries

- 255 of 257 cities are mapped to GeoCore; 2 unresolved cities account for 5 of
  1,557 facilities. Do not invent mappings for deployment.
- Directory Platform has a second adapter proof, not a second production directory.
- The current release is designed for one Brandenburg catalog and SQLite shared
  hosting; multi-region production scale is not yet proven.
- GA4 and Clarity integration exists but must remain disabled because Consent Layer
  does not exist.
- Existing historical legal/hosting documents that say no analytics code exists
  predate the conditional integration. The accurate technical state is: integration
  present, scripts disabled when IDs are blank.
- The privacy page intentionally exposes unknown hosting/mail/retention facts. That is
  technically honest, but it blocks unconditional production approval until facts are
  supplied and the text is approved.
- The operator name is abbreviated in the current Impressum and requires owner
  confirmation; this document makes no legal conclusion.
- nginx rules in the repository are references only and are not automatically loaded
  by Laravel or the package.
- Real production permissions, provider logs, AWStats behavior, backup retention and
  restore evidence cannot be proved from Git.
- Search Console and Bing ownership/coverage are post-deployment operational tasks.
- The package builder's arbitrary historical-ref reproducibility depends on a clean
  working tree for four deployment resources; this package was prepared from a clean
  matching baseline.

---

## 15. Audit evidence

| Check | Result |
|---|---|
| PHP runtime | PASS — 8.2.27 |
| Laravel runtime | PASS — 12.64.0 |
| Required local extensions | PASS, including `pdo_sqlite`, `sqlite3`, `curl`, `zip`, `mbstring`, XML and OpenSSL |
| `composer validate --strict` | PASS |
| Production Composer install dry run | PASS — lock installable, `--no-dev` plan valid |
| Clean production Composer installation | PASS inside package self-check |
| `composer check-platform-reqs --no-dev` | PASS |
| `composer audit --locked --no-dev` | PASS — no known security advisories at check time |
| Production package self-check | PASS — 12/12 including real Composer install |
| Package checksum re-verification | PASS — 6,316 checked, 0 failures |
| Full Laravel test suite | PASS — 215 tests, 3,183 assertions |
| Application routes | PASS — 33 routes loaded |
| Isolated production config cache | PASS — production, debug OFF, canonical URL, locale `de`; temporary cache removed |
| Local SQLite integrity | PASS — `ok` |
| Local foreign-key check | PASS — 0 violations |
| Local SQLite SHA-256 before checks | `5F37FB8A12743DEF3A41C96A55D415D164A91E009DE6A20D5E33423AD34A4423` |
| Local SQLite SHA-256 after checks | `5F37FB8A12743DEF3A41C96A55D415D164A91E009DE6A20D5E33423AD34A4423` |
| OG asset | PASS — PNG, 1254 × 1254, 624,884 bytes |
| Sitemap/robots/canonical/SEO tests | PASS |
| Analytics disabled with empty config | PASS in Feature test; must be reconfirmed live |
| Local temporary config/probe files | Removed |
| Runtime/config hard-coded Windows paths | None found; package scan rejects them |
| Production server deployment | NOT PERFORMED |
| Production live acceptance | PENDING |

The first isolated config-cache attempt used an absolute Windows cache override, which
Laravel interpreted relative to the application base and rejected. It created no
cache. The check was repeated with a safe relative isolated cache path and passed;
the temporary cache was cleared. This was a test-harness correction, not an
application defect.

The full package command exceeded the outer command-output timeout while ZIP creation
continued in its protected build process. The process completed, wrote
`archivesCreated=true`, and was followed by an independent 6,316-file checksum and ZIP
root verification. No partial package is being approved.

---

## 16. Exact next manual action

**Do not upload files yet.** The next action belongs to the operator/hosting owner:

1. complete the pending operator, hosting, server-log, mailbox, retention, backup and
   AV-Vertrag facts privately;
2. confirm the exact live `<CURRENT_APP_DIR>`, `<PUBLIC_DIR>` and effective
   `<CURRENT_DB_PATH>`;
3. decide whether the current DB must be externalized to stable `<DB_PATH>`;
4. confirm provider maintenance and a consistent SQLite backup/restore method;
5. export the active nginx configuration and decide how `webstat/` and the public
   backup HTML are handled;
6. record explicit owner approval.

Only after those six items are complete should FileZilla upload begin at **Step 5**,
using the verified `8bc17b22` archives and switching `index.php` last.

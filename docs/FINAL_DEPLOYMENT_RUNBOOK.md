# PflegeIndex Final Deployment Runbook

This runbook applies to release commit
`fd01a71fb5084c22aea83ba6d50bcc00b7769f72`. It prepares a controlled shared
hosting/FileZilla deployment. It does not authorize deployment by itself.

Use these placeholders throughout:

- `<APP_DIR>` — new private Laravel release directory outside the webroot;
- `<CURRENT_APP_DIR>` — currently deployed private Laravel directory;
- `<PUBLIC_DIR>` — document root for `pflegeindex.com`;
- `<DB_PATH>` — external production SQLite path from the protected `.env`;
- `<BACKUP_DIR>` — protected backup directory outside `<PUBLIC_DIR>`;
- `<PREVIOUS_COMMIT>` — recorded commit of the working production release.

Never paste secrets into this document, terminal transcripts or support
tickets. Run Laravel commands from `<APP_DIR>`, not `<PUBLIC_DIR>`.

## 1. Preconditions

**Action:** Obtain the three `fd01a71f` ZIP archives and compare their byte
sizes and SHA-256 values with `docs/FINAL_RELEASE_CANDIDATE_REPORT.md`. Confirm
that PHP 8.2+, PDO SQLite and SQLite3 are available.

**Expected:** All three hashes match; the server satisfies the PHP platform
requirements.

**PASS/FAIL:** PASS only with three exact matches and all required extensions.

**On failure:** Stop. Re-download the artifact or correct the hosting runtime;
never upload an archive with a mismatched hash.

## 2. Owner confirmations

**Action:** Complete `docs/OWNER_LEGAL_HOSTING_QUESTIONS.md`, approve the
operator details, final Impressum and final Datenschutzerklärung, and record
the responsible release approver.

**Expected:** Every launch-relevant fact has evidence and no legal text relies
on an `UNKNOWN` value.

**PASS/FAIL:** PASS only after explicit owner approval.

**On failure:** Keep the release at CONDITIONAL GO and do not make it public.

## 3. Hosting confirmations

**Action:** Ask the provider to confirm `<PUBLIC_DIR>`, a private directory for
`<APP_DIR>`, PHP 8.2 selection, nginx vhost access, SQLite permissions, backup
location, log policy and available disk space.

**Expected:** The private core, `.env`, database and backups cannot be served
over HTTP; nginx changes can be applied by the provider/control panel.

**PASS/FAIL:** PASS only with confirmed paths and sufficient free space for the
new release, backup and rollback copy.

**On failure:** Stop and resolve the hosting limitation before upload.

## 4. Maintenance preparation

**Action:** Record the deployment window and `<PREVIOUS_COMMIT>`. If SSH is
available, run:

```bash
cd <CURRENT_APP_DIR>
php artisan down --retry=60
```

For FileZilla-only hosting, use the provider's maintenance control or schedule
a short maintenance window before copying SQLite.

**Expected:** Public writes and admin updates are stopped while backup and
switching occur.

**PASS/FAIL:** PASS when maintenance is visible and the previous release is
recorded.

**On failure:** Do not copy or replace production data.

## 5. Production backup

**Action:** Create a new timestamped directory below `<BACKUP_DIR>`. Copy the
current SQLite file, `.env`, current front controller and any provider nginx
configuration/export. Record file sizes and SHA-256 checksums. Do not put the
backup in `<PUBLIC_DIR>` or the new release.

**Expected:** The backup files are non-empty, protected and independently
readable. SQLite `PRAGMA integrity_check;` returns `ok` on a protected copy.

**PASS/FAIL:** PASS only after checksum and SQLite verification.

**On failure:** Leave the old release in place and restore public access.

## 6. Upload private core

**Action:** Extract `pflegeindex-private-core-fd01a71f.zip` locally. In
FileZilla, create a uniquely named `<APP_DIR>` outside `<PUBLIC_DIR>` and upload
the archive contents directly into it. `artisan`, `app`, `bootstrap`, `config`,
`routes` and `vendor` must be directly below `<APP_DIR>`.

**Expected:** There is no extra `private-core` wrapper directory. No `.env`,
SQLite, backup, test or Git metadata was supplied by the ZIP.

**PASS/FAIL:** PASS when the directory layout matches the manifest and all
uploads complete without failed transfers.

**On failure:** Delete only the new incomplete release directory after its
absolute path is verified; keep the current release untouched.

## 7. Upload public webroot

**Action:** Extract `pflegeindex-public-webroot-fd01a71f.zip` locally. First
prepare its `index.php` in a protected deployment copy by replacing
`__PRIVATE_CORE_PATH__` with the absolute Unix path to `<APP_DIR>`. Upload the
public contents to a temporary webroot/staging location or during the approved
switch window. Upload `.htaccess` explicitly in FileZilla because it is hidden.

**Expected:** `index.php`, `.htaccess`, `favicon.svg`, logos and `assets/` are
directly under `<PUBLIC_DIR>`. The deployed `index.php` contains the correct
private Unix path and no local Windows path.

**PASS/FAIL:** PASS when all nine public files are present and the placeholder
is absent from the deployed front controller.

**On failure:** Restore the backed-up public files/front controller before
ending maintenance.

## 8. Environment verification

**Action:** Preserve the current production `APP_KEY`. Create or update the
private `<APP_DIR>/.env` using the safe template. Verify without printing
values:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pflegeindex.com
DB_CONNECTION=sqlite
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=30
```

Confirm secure admin-session cookie settings and the intended cache, queue and
mail drivers.

**Expected:** Laravel reads the production environment and `<DB_PATH>` is an
absolute private path outside both release and webroot.

**PASS/FAIL:** PASS when every required key is present and the real `APP_KEY`
was neither changed nor disclosed.

**On failure:** Restore the backed-up `.env`; do not build config cache.

## 9. File permissions

**Action:** Give the PHP/web-server user write access only to `storage/`,
`bootstrap/cache/`, `<DB_PATH>` and its private parent directory. Keep source,
`.env`, backups and database inaccessible to public users. Never use mode 777.

**Expected:** Laravel can create cache/log/session files and SQLite can create
its required sidecar files; public HTTP cannot read private files.

**PASS/FAIL:** PASS when required paths are writable by PHP and protected from
HTTP.

**On failure:** Correct owner/group permissions through the provider; do not
weaken the whole application tree.

## 10. Cache clear/build

**Action:** After `.env` verification, run:

```bash
cd <APP_DIR>
php artisan optimize:clear
php artisan optimize
```

**Expected:** Configuration, event, route and view caches build without error.

**PASS/FAIL:** PASS when both commands exit successfully.

**On failure:** Run `php artisan optimize:clear`, inspect sanitized logs and
keep maintenance active.

## 11. SQLite verification

**Action:** Select Scenario A or B from
`DATABASE_DEPLOYMENT_DECISION.md`. Do not upload the local development SQLite.
For an existing database, preserve it and run only after the verified backup:

```bash
cd <APP_DIR>
php artisan migrate:status
php artisan migrate --force
php artisan migrate:status
```

Then verify `PRAGMA integrity_check;`, foreign keys and expected application
counts using protected server tooling. Do not run GeoCore import commands.

**Expected:** Migrations are current, integrity is `ok`, and existing data is
preserved.

**PASS/FAIL:** PASS only when the selected scenario and all results are
recorded.

**On failure:** Keep maintenance active and restore the verified SQLite backup;
do not use a blind migration rollback.

## 12. nginx redirect application

**Action:** Give the hosting administrator
`deployment/nginx-canonical-redirect.conf`. Apply its HTTP vhost for both host
names, its HTTPS redirect-only `www` vhost, and keep `pflegeindex.com` as the
serving HTTPS vhost. Existing provider TLS certificate directives remain
provider-managed.

Verify with:

```bash
curl -I "http://pflegeindex.com/path?x=1"
curl -I "http://www.pflegeindex.com/path?x=1"
curl -I "https://www.pflegeindex.com/path?x=1"
curl -I "https://pflegeindex.com/path?x=1"
```

**Expected:** Each non-canonical URL returns one direct 301 to
`https://pflegeindex.com/path?x=1`; the canonical HTTPS host does not redirect
for host normalization. There is no loop and no explicit `:443`.

**PASS/FAIL:** PASS only after configuration validation/reload and all four
live checks. The repository file alone does not prove production application.

**On failure:** Revert the vhost change through the provider and keep the old
known-good routing.

## 13. Application startup

**Action:** While maintenance remains active, run:

```bash
cd <APP_DIR>
php artisan about --only=environment
php artisan route:list
```

Then switch the document root/front controller to the new release and run:

```bash
php artisan up
```

**Expected:** Laravel boots in production with debug off; routes load; the new
front controller resolves the private core.

**PASS/FAIL:** PASS when startup has no exception and `/up` becomes reachable.

**On failure:** Re-enable maintenance if possible and execute rollback.

## 14. Smoke tests

**Action:** Check `/up`, `/`, `/brandenburg.html`, one district, one city, one
facility, `/pflegeheime.html`, admin login and a guest-protected admin URL.
Check search, pagination, CSS/JS and facility contact links on desktop and
mobile.

**Expected:** Public pages return 200; `/up` is exactly plain-text `OK`; admin
authentication behaves correctly; assets and navigation work.

**PASS/FAIL:** PASS only with no 5xx response, broken asset or authentication
regression.

**On failure:** Record the URL/status and sanitized log time, then rollback if
the issue affects core use or security.

## 15. SEO checks

**Action:** Verify `/robots.txt`, `/sitemap.xml`, canonical URLs, pagination,
Open Graph and JSON-LD on representative pages. Request
`/assets/og-image.png` directly.

**Expected:** Production HTTPS URLs are used; OG image returns direct HTTP 200
as `image/png`; no admin/legal exclusions are accidentally indexed; sitemap is
valid XML.

**PASS/FAIL:** PASS when the deployed output matches automated release tests.

**On failure:** Keep a record and rollback for canonical/robots/sitemap issues
that could cause immediate indexing damage.

## 16. Security checks

**Action:** Attempt HTTP requests for `/.env`, the SQLite filename, storage,
backup names, `.git`, Composer files and diagnostic scripts. Inspect response
headers on public, admin and `/up` routes. Confirm debug output and stack traces
are absent.

**Expected:** Private targets are unavailable; security and `X-Robots-Tag`
headers are present where expected; `/up` reveals no diagnostics.

**PASS/FAIL:** PASS only when no secret/private file is downloadable.

**On failure:** Take the site out of public service immediately and correct the
document root/vhost before retrying.

## 17. Rollback procedure

**Action:** If any release acceptance criterion fails, enable maintenance,
preserve sanitized failure logs, restore the previous public front controller
and `<CURRENT_APP_DIR>`, restore `.env` only if changed, and restore SQLite only
if it changed. Rebuild caches for the old release and retest it.

**Expected:** The previous release and its data return to the recorded state.

**PASS/FAIL:** PASS when old smoke tests pass and the restored database checksum
matches the verified backup when a restore was required.

**On failure:** Keep maintenance enabled and escalate to the hosting
administrator; do not attempt further database mutations.

## 18. Release acceptance

**Action:** Complete `docs/RELEASE_VERIFICATION.md`, record release commit,
artifact hashes, database scenario, backup identifier, approver and deployment
time.

**Expected:** Every mandatory checkbox is complete and no unresolved security,
data-integrity, legal or core-function issue remains.

**PASS/FAIL:** PASS only with explicit owner/technical acceptance.

**On failure:** Keep the result conditional or roll back according to impact.

## 19. Post-release monitoring

**Action:** For the agreed observation period, monitor `/up`, representative
pages, disk space, Laravel daily logs, provider access/error logs and backup
jobs. Recheck after 15 minutes, 1 hour and the next business day. Keep the old
release and verified backup until acceptance is stable.

**Expected:** No new repeated errors, unexpected redirects, disk growth,
failed backups or user-facing regressions.

**PASS/FAIL:** PASS after the observation period and a confirmed backup job.

**On failure:** Classify impact, preserve evidence and roll back immediately
for availability, security or data-integrity failures.

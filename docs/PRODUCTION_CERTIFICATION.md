# PflegeIndex Production Readiness Certification

**Audit date:** 2026-07-22

**Audited branch:** `main`

**Audited application baseline:** `c3169b9622c5e740c813d99594091b5ba1beea6d`

**Decision:** **NOT CERTIFIED FOR PUBLIC RELEASE YET — conditional release candidate**

**Overall production readiness:** **86%**

## 1. Executive Summary

The local release candidate is functionally stable: all 202 automated tests pass,
the SQLite database remained unchanged, GeoCore integrity is confirmed, representative
public requests have bounded query counts, and the local application contains the
expected SEO, security-header, health-check and social-image hardening.

The first public release must not be declared complete yet. The current public server
is running an older application state, the repository worktree is not clean, the full
Pint check is not green, release documentation contains stale baseline information,
and the privacy notice cannot be certified until hosting and mailbox facts are supplied
by the operator. These are release-process and trust blockers; no new application
feature is required.

## 2. Certification Scope and Method

The audit covered:

- the Git index, tracked files, ignored build output and recent local commits;
- production environment templates and Laravel configuration;
- middleware, webroot packaging rules, authentication and public/admin routes;
- representative production-mode HTTP requests through the local Laravel kernel;
- the live `pflegeindex.com` host through read-only HTTP requests;
- public Blade templates, responsive CSS, navigation, forms and trust wording;
- the deployment, operations, release, limitations, status and README documents;
- the complete automated test and requested quality-check suite.

No migration, import, mapping operation, schema update or database write was performed.
The application SQLite SHA-256 remained
`5F37FB8A12743DEF3A41C96A55D415D164A91E009DE6A20D5E33423AD34A4423`.

## 3. Repository

### Confirmed

- `.env`, SQLite, logs, deployment ZIP files, `vendor`, runtime cache and
  `deployment/server-diagnostics.php` are excluded from Git.
- A tracked-file scan found no private keys, access keys, passwords, tokens or
  hard-coded application secrets. Matches for the word `password` were normal
  password-handling code and command arguments, not credentials.
- The two local commits ahead of `origin/main` are the approved GeoCore application
  commit and its final validation report. No unrelated tracked change was found.
- The local `.env` was neither inspected for values nor modified.

### Release blockers and cleanup

- The worktree is not clean because
  `docs/audits/PRODUCTION_READINESS_AUDIT.md` is an existing untracked file. It was
  not changed during this sprint, but it must be deliberately committed, archived
  outside the worktree or removed by the owner before building the release.
- `main` was already two commits ahead of `origin/main` at audit start. The approved
  commits must be pushed before any server update is built from `origin/main`.
- Ignored deployment output under `build/production/` contains old ZIP archives and
  a residual `.self-check-*` directory. These are not Git risks, but the old archives
  must not be mistaken for the certified release. Build a new package from the final
  clean release commit.
- A local ignored `storage/logs/laravel.log`, local SQLite and
  `deployment/server-diagnostics.php` exist as expected for development/operations;
  the production-package safeguards exclude them.

## 4. Environment and Production Configuration

Both `.env.production.example` and `deployment/.env.production.template` prescribe:

| Setting | Required repository value | Assessment |
| --- | --- | --- |
| `APP_ENV` | `production` | Correct |
| `APP_DEBUG` | `false` | Correct |
| `APP_URL` | `https://pflegeindex.com` | Correct |
| `APP_TIMEZONE` | `Europe/Berlin` | Correct |
| `CACHE_STORE` | `database` | Correct for the current SQLite deployment |
| `SESSION_DRIVER` | `database` | Correct for protected admin sessions |
| Session flags | encrypted, Secure, HttpOnly, SameSite=Lax | Correct |
| `QUEUE_CONNECTION` | `sync` | Correct for the current no-worker hosting model |
| `MAIL_MAILER` | `log` | No application mail delivery; public contact is mailto-based |
| `LOG_CHANNEL` / `LOG_STACK` | `stack` / `daily` | Correct |
| Log retention | 30 days | Correct |
| `LOG_LEVEL` | `warning` | Preserved |

A local bootstrap with production overrides reported Laravel 12.64.0, PHP 8.2.27,
production environment, debug off and Europe/Berlin. Actual production values for
all non-public `.env` fields, session encryption, database path, writable directories
and cache state still require the deployment checklist on the server; repository
templates are not proof of deployed secret configuration.

## 5. Security

### Ready in the release candidate

- Debug defaults to off and both production templates explicitly disable it.
- A production-mode unknown route returned a generic HTTP 404 without a stack trace.
- Public pages do not start Laravel sessions and tests confirm no `Set-Cookie` header.
- Admin routes use a dedicated session/CSRF middleware group, authentication,
  `EnsureAdmin`, login throttling and validated URL inputs.
- Response middleware adds `nosniff`, `SAMEORIGIN`, strict-origin referrer policy,
  a restrictive camera/microphone/geolocation policy and HSTS on HTTPS.
- Admin and `/up` responses receive `X-Robots-Tag: noindex, nofollow, noarchive`.
- The deployment layout places Laravel core, `.env`, SQLite, storage, logs and backups
  outside the document root. The production `.htaccess` rejects hidden-file requests.
- The public application has no upload endpoint. The parser upload is authenticated,
  CSRF-protected, validated and stored through the protected admin workflow.
- Live probes to `/.env`, `/.git/config`, `/database/database.sqlite` and
  `/storage/logs/laravel.log` returned HTTP 404.

### Remaining hardening observations

- The live server returns duplicate `Strict-Transport-Security` headers because both
  nginx and Laravel add HSTS. Consolidate this during server configuration review.
- The live server exposes `Server: nginx/1.20.2` and `X-Powered-By: PHP/8.2.27`.
  Version-header suppression is advisable but does not by itself block release.
- No Content Security Policy is configured. Existing assets are local in the release
  candidate, so CSP is a later defence-in-depth improvement rather than a release
  blocker.

## 6. Performance

### Confirmed

- `composer.json` sets `optimize-autoloader: true`, and deployment instructions use
  `composer install --no-dev --optimize-autoloader`.
- The deployment checklist runs `php artisan optimize`, which builds config, event,
  route and Blade view caches after production `.env` verification.
- Route definitions are compatible with route caching; no closure-based public route
  remains except framework/bootstrap health configuration already exercised by tests.
- DirectoryCore page tests explicitly guard constant query counts and eager loading.
- Representative production-mode local requests showed 0–9 queries per page and no
  repeated SQL statements. Directory, filtered search, Brandenburg, District, City,
  Facility and legal pages all returned their expected status.
- Current result listings are paginated at 24 entries.

### Deployment verification still required

- Local development cache files are not evidence that production route/config/view
  caches are active. Run `php artisan optimize` on the final deployed release and
  verify cache status there.
- Cold local timings are not production benchmarks. Measure the final host after cache
  warm-up; the Brandenburg page is the largest representative HTML response because
  it includes the complete city navigation on its first page.

## 7. SEO

### Local release candidate

- The final GeoCore validation audited 1,899 unique sitemap URLs with no duplicates,
  missing canonical metadata or broken internal links.
- URL counts were: 1 homepage, 1 directory, 1 region, 18 districts, 257 cities,
  1,557 facilities, 63 lexicon URLs and 1 indexed static project page.
- Canonical host and HTTPS redirects are covered by tests.
- Filtered directory URLs use `noindex,follow`; the unfiltered catalogue remains
  indexable.
- Pagination uses validated page numbers, self-canonical URLs and page-specific title,
  description, Open Graph and CollectionPage URL where applicable.
- Homepage, region, district, city and facility metadata include the intended canonical,
  Open Graph and JSON-LD structures. The common image is the local static
  `public/assets/og-image.png` (1254×1254 PNG, 624,884 bytes).
- `/robots.txt`, `/sitemap.xml`, `/up`, 404 handling and the known permanent facility
  redirects are covered by automated tests.

### Current live-server drift — blocks certification of the deployed site

- `https://pflegeindex.com/assets/og-image.png` currently returns HTTP 404 although
  the local release contains the asset.
- The live `/up` still returns Laravel's HTML health page and loads Bunny Fonts and
  jsDelivr. The local release correctly returns the two-byte plain-text body `OK`
  without database access, cookies or external resources.
- `http://www.pflegeindex.com/` redirects first to
  `https://www.pflegeindex.com:443/` and only then to the canonical host. Configure
  nginx to perform one direct 301 to `https://pflegeindex.com/` and avoid the explicit
  default port. The non-www HTTP host also exposes `:443` in its redirect target.
- Homepage, robots and sitemap returned HTTP 200 on the live host, but they represent
  the older deployed release and are not certification evidence for the local candidate.

No SEO code was modified in this sprint. The local fixes must be deployed, then verified
on the public host before launch approval.

## 8. UX and Accessibility

### Confirmed

- Homepage, directory/search, Brandenburg, District, City, Facility and legal page
  requests render successfully in production mode.
- Responsive CSS has desktop, tablet (`960px`) and mobile (`760px` and `500px`)
  breakpoints. Grids collapse appropriately and navigation changes to a mobile menu.
- Facility contact actions move directly below the heading on mobile, have a minimum
  44px target height and appear only for available phone, website or email values.
- Forms use visible labels or screen-reader labels. Public search is a GET form;
  protected state-changing admin forms include CSRF protection.
- Search has a clear empty state, reset action and preserved filters through pagination.
- Breadcrumbs, active navigation, pagination labels and major section labels are
  represented semantically and covered by tests.
- Focus-visible styles and reduced-motion handling are present.

### Known UX limitations

- This audit did not include a physical-device or assistive-technology session. A final
  smoke test at desktop, tablet and mobile widths remains part of post-deployment QA.
- There is no skip link to main content.
- The mobile menu button changes `aria-expanded` but has no `aria-controls`, does not
  update its accessible label and has no explicit Escape-key close behavior.
- `public/favicon.ico` is a zero-byte legacy fallback. The layouts explicitly use the
  valid SVG favicon, so this is not a functional blocker, but the empty fallback should
  be replaced or removed in a later asset-cleanup task.

## 9. Trust and Legal Pages

### Confirmed

- `/impressum.html`, `/datenschutz.html` and `/ueber-das-projekt.html` return HTTP 200,
  have canonical metadata and are linked from the footer.
- Impressum and Datenschutz use `noindex,nofollow`; the project page remains indexable.
- The Impressum contains operator, address and contact blocks and clearly states that
  PflegeIndex is not an official register or quality assessment.
- Facility pages distinguish `Amtliche Grunddaten` from editorial contact and
  description additions.
- `Datenfehler melden` is available on every facility page and pre-populates the
  facility name and canonical URL in a mailto message.
- Contact, telephone and external website actions are only emitted when corresponding
  data exists; external new-tab links use `noopener`.

### Trust blockers

- The Datenschutz page is technically outdated: it says `/up` can load Bunny Fonts and
  jsDelivr, while the audited release candidate deliberately returns plain text with no
  third-party assets.
- The existing privacy audit identifies unresolved operator facts for hosting provider,
  processing location, provider access/error-log retention, mailbox provider,
  forwarding, mailbox retention and backup processing. Until supplied, the privacy
  notice cannot be certified as complete and factually aligned.
- The statement that email data is deleted after completion is not backed by a confirmed
  mailbox retention/deletion procedure.
- Legal sufficiency of the displayed operator identity and cross-border details requires
  explicit owner/legal approval; automated tests only prove presence and consistency of
  the current text.

## 10. Architecture

- DirectoryCore remains framework-independent and is protected by allowlist tests.
- GeoCore and PflegeIndex adapters retain their documented boundaries.
- `EntryRepository`, `ListEntries` and the frozen public Directory Platform API remain
  unchanged by this certification sprint.
- The full architecture test suite passed. No dependency violation was found.
- GeoCore final validation remains at 255 of 257 mapped cities and 1,552 of 1,557
  facilities with municipality coverage; the five known unresolved facilities are
  documented and do not break public listings.

## 11. Operations and Documentation Consistency

| Document | Assessment |
| --- | --- |
| `docs/DEPLOYMENT_CHECKLIST.md` | Operationally current; covers clean tree, backup, private paths, optimized install, caches, permissions, rollback and post-deployment checks. |
| `docs/OPERATIONS.md` | Current for daily logs, 30-day retention, backups, monitoring and incident recovery. |
| `docs/RELEASE_NOTES.md` | Stale: records 97 tests / 2,365 assertions and predates the final GeoCore and hardening baseline. |
| `docs/KNOWN_LIMITATIONS.md` | Architecturally consistent, but does not quantify the two remaining review cities/five facilities or distinguish accepted release limitations from resolved work. |
| `docs/PROJECT_STATUS.md` | Stale: records 97 tests / 2,365 assertions, says local PHP/Composer are unavailable and describes package creation as blocked, which is no longer true. |
| `README.md` | Deployment flow is broadly consistent, but the instruction to add real operator data is stale because operator blocks now exist; it should instead require owner verification. |

An additional inconsistency exists in `docs/PRODUCTION_PACKAGE.md`, which still describes
the old single-file log configuration even though production now uses daily rotation.
It was not one of the six required documents, but it should be corrected before a new
package is built.

## 12. Quality Checks

| Check | Result |
| --- | --- |
| `php artisan test` | **PASS — 202 tests, 3,070 assertions** |
| `composer validate --strict` | **PASS** |
| `composer audit --locked --no-interaction` | **PASS — no known security vulnerability advisories** |
| `git diff --check` before report | **PASS** |
| `vendor/bin/pint --test` | **FAIL** |

Pint reports pre-existing formatting deviations in:

- `app/Http/Controllers/HomeController.php` (`binary_operator_spaces`);
- ignored `deployment/server-diagnostics.php` (four style rules).

Neither file was modified in this sprint. The deployment checklist requires Pint to
pass for the exact release commit, so the tracked controller deviation must be handled
in a separate, narrowly scoped formatting task. The ignored diagnostic file should be
removed before the release check or checked separately because it is not part of the
release package.

## 13. Known Limitations Accepted for v1.0

- Two legacy city records remain unresolved in GeoCore, affecting five facilities;
  state-level and city-level public discovery remains available.
- GeoCore's documented Eloquent boundary and reverse City relation remain accepted
  architecture debt.
- FuneralIndex remains an architecture proof only.
- A real-device/accessibility smoke test and production cache/performance confirmation
  can only be completed after deploying the exact candidate.
- Provider-level monitoring, access-log retention, backups and mailbox processing are
  external operational facts and cannot be inferred from repository configuration.

## 14. Release Blockers and Required Closure

| Priority | Finding | Required action | Verification |
| --- | --- | --- | --- |
| P0 | Current production is older than the audited release (`og:image` 404 and old HTML `/up`) | Push the approved commits, rebuild a clean package and deploy the exact certified commit after backup | Verify asset HTTP 200/image/png, `/up` plain `OK`, public smoke tests |
| P0 | Privacy/hosting/mailbox facts are incomplete and Datenschutz is technically stale | Obtain operator facts and complete a separate legally reviewed privacy update | Owner approval plus implementation-to-copy comparison |
| P0 | Canonical redirect is two hops for HTTP `www` and exposes `:443` | Correct the nginx/domain redirect to one direct 301 to `https://pflegeindex.com/` | Test all four scheme/host variants without following redirects |
| P0 | Worktree is not clean and current build archives are stale | Resolve the untracked audit file and generate the package from a clean final commit | Empty `git status --short`; manifest commit equals release commit |
| P1 | Full Pint check fails | Apply a separate formatting-only fix or define a release-scoped exclusion for the ignored diagnostic file | `vendor/bin/pint --test` exits 0 |
| P1 | Release/status/package documentation is stale | Update factual counts, current phase, package availability and daily logging wording | Cross-document review against final commit |
| P1 | Production caches, permissions and backups are not yet proven for the exact release | Execute every deployment and release-verification checklist item | Recorded backup, cache status, permissions and post-deploy results |
| P2 | Duplicate HSTS and version headers on live nginx | Consolidate HSTS ownership and suppress unnecessary version disclosure | Header inspection |
| P2 | Accessibility and favicon fallbacks remain incomplete | Add skip link/menu semantics and replace/remove empty ICO in a later polish task | Keyboard/screen-reader and asset smoke test |

## 15. Release Recommendation

**Recommendation: NO-GO for declaring the first public production release complete
today.**

The application code is a strong release candidate, but certification must remain
conditional until all P0 items are closed. After the privacy facts and copy are approved,
the worktree and documentation are clean, Pint is green, and the exact final commit is
deployed with a verified backup, rerun the production URL/header/asset checks and the
post-deployment checklist. If those checks pass, no further feature development is
required for the v1.0 public launch.

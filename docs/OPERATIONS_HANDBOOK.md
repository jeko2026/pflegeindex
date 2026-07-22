# Operations Handbook

## 1. Purpose, scope and operating rule

**Applicability: Reusable for Directory Platform**

This handbook defines the routine operating standard for PflegeIndex and the
minimum reusable baseline for future Directory Platform catalogs. It connects
monitoring, backups, editorial work, releases, incidents and maintenance into
one lifecycle.

This document does not replace task-specific checklists. When performing a
deployment or release verification, the operator must follow the referenced
checklist line by line.

Every subsection inherits the nearest `Applicability` label unless it contains
its own label. Project profiles are `PflegeIndex only`; the surrounding process
is `Reusable for Directory Platform`.

Core operating rules:

- protect user access and data integrity before restoring availability;
- never experiment on the only production database or backup;
- record the deployed commit and database backup before every change;
- do not edit tracked application code directly on production;
- do not expose `.env`, SQLite, logs, backups, source code or temporary tools;
- do not print credentials, tokens, cookies or private log contents in reports;
- use maintenance mode when database integrity or concurrent writes are at
  risk;
- restore the last verified state before attempting a broad emergency fix;
- record every incident, rollback and exceptional production action.

## 2. Current operations documentation audit

**Applicability: PflegeIndex only**

### 2.1 Existing documents

| Document | Existing responsibility | Operational status |
|---|---|---|
| `DEPLOYMENT_CHECKLIST.md` | Paths, release prerequisites, maintenance mode, backup, code update, migrations, caches, permissions, go-live and rollback | Authoritative execution checklist for deployment and rollback |
| `OPERATIONS.md` | Daily/weekly checks, application logs, backup retention, dependency updates and general incident response | Routine technical reference; retained as the concise guide |
| `RELEASE_VERIFICATION.md` | Public pages, administration, legal pages, SEO and operational verification | Authoritative sign-off sheet after deployment or rollback |
| `PRODUCTION_READY.md` | Production fixes, asset versions, Open Graph asset, redirects and release-specific live checks | Historical/release-specific readiness record, not a permanent SOP |
| `PROJECT_STATUS.md` | Platform state, API freeze, architecture debt and outstanding release actions | Project snapshot, not an operational checklist |

### 2.2 Documented coverage

The existing set already covers:

- private application/public webroot separation;
- production environment requirements;
- backup before migration or data mutation;
- SSH/Git and FileZilla deployment paths;
- safe Composer installation;
- migration boundaries;
- Laravel cache rebuild;
- permissions;
- daily and weekly health checks;
- daily log rotation with 30-day retention;
- recommended backup retention;
- dependency update rules;
- public page and admin verification;
- canonical redirects, sitemap, robots and Open Graph image;
- rollback to a recorded commit and verified SQLite backup;
- basic security incident escalation.

### 2.3 Overlap

| Topic | Documents containing it | Rule for future use |
|---|---|---|
| Post-deployment checks | Deployment Checklist, Release Verification, Production Ready | Execute `RELEASE_VERIFICATION.md`; other documents explain context |
| Backups | Deployment Checklist, Operations | Deployment Checklist controls release backups; Operations controls routine retention and restore tests |
| Logs | Deployment Checklist, Operations, Release Verification | Operations defines policy; Release Verification checks for new release errors |
| Redirects and assets | All except Project Status in different detail | Release Verification is the live acceptance checklist |
| Incident recovery | Operations and Deployment Checklist | This handbook selects the playbook; Deployment Checklist provides exact rollback steps |
| Production readiness | Production Ready and Project Status | Treat both as dated status records, not evergreen instructions |

### 2.4 Gaps closed by this handbook

Before this handbook there was no single documented standard for:

- explicit monthly operations;
- incident severity and escalation roles;
- symptom-specific incident playbooks;
- the actions required after a rollback;
- a complete release lifecycle from development through maintenance;
- routine editorial operations;
- a consolidated Production Health checklist;
- monitoring evidence and incident record fields;
- the relationship between reusable Platform practice and PflegeIndex-specific
  checks.

Still requiring operator decisions:

- named primary and backup operator;
- hosting-provider escalation channel;
- recovery time objective (RTO);
- recovery point objective (RPO);
- approved observation period after a release;
- certificate renewal responsibility;
- hosting access/error-log retention;
- off-site backup location and encryption policy;
- notification channel for critical incidents.

Do not invent these values. Record them in the protected operator runbook or
approved hosting documentation when confirmed.

## 3. Source-of-truth hierarchy

**Applicability: Reusable for Directory Platform**

Use documents in this order:

1. approved release instructions for the exact version;
2. `DEPLOYMENT_CHECKLIST.md` for deployment and rollback execution;
3. `RELEASE_VERIFICATION.md` for live sign-off;
4. this handbook for schedules, incident selection and lifecycle policy;
5. `OPERATIONS.md` for concise routine technical guidance;
6. release-specific readiness and project-status documents for historical
   context.

If two instructions conflict, stop. Do not combine partial procedures. Record
the conflict and resolve it in documentation before changing production.

## 4. Roles and evidence

**Applicability: Reusable for Directory Platform**

One person may hold several roles, but each action must have a responsible role.

| Role | Responsibility |
|---|---|
| Operator | Routine health checks, backups, disk, logs and deployment execution |
| Release owner | Approves commit/artifact, migration scope and go/no-go decision |
| Data steward | Approves imports, editorial publication and data recovery |
| Incident lead | Coordinates containment, recovery, communication and timeline |
| Privacy/security contact | Handles suspected disclosure, credential exposure or unauthorized access |
| Hosting contact | Resolves DNS, TLS, web-server, storage and provider-level incidents |

Each operational record should contain:

- UTC/local timestamp and timezone;
- environment and hostname;
- operator role;
- deployed full commit hash;
- check or incident result;
- affected URLs/components;
- sanitized evidence;
- backup identifier when relevant;
- action taken and next review time.

Never place secrets, raw cookies, full `.env`, administrator passwords or
unredacted personal data in the record.

## 5. Daily operations

**Applicability: Reusable for Directory Platform**

Perform once per operating day and after a hosting alert.

- [ ] Liveness endpoint returns its exact expected status/body.
- [ ] Homepage returns HTTP 200 over the canonical HTTPS host.
- [ ] One representative listing and one detail page return HTTP 200.
- [ ] Local CSS and JavaScript load successfully.
- [ ] No maintenance/debug/error page is visible publicly.
- [ ] New Laravel `ERROR`/`CRITICAL` entries and repeated warnings are reviewed.
- [ ] Scheduled database backup exists, is non-empty and is outside webroot.
- [ ] Free disk is sufficient for database growth, logs, cache and the next
      backup.
- [ ] Unexpected increases in HTTP 500 or unavailable responses are escalated.
- [ ] Pending editorial/contact review work is visible to the responsible role;
      no automatic publication occurred.

### PflegeIndex daily profile

**Applicability: PflegeIndex only**

- `/up` must return HTTP 200 with the exact plain-text body `OK`.
- `/up` is liveness only; it does not prove SQLite or external services work.
- Check `/`, one populated City page and one Facility page.
- Confirm the latest SQLite backup is protected outside the document root.
- Check `storage/logs/laravel-YYYY-MM-DD.log` privately.
- Keep `GA4_MEASUREMENT_ID` and `CLARITY_PROJECT_ID` empty until an approved
  Consent Layer and privacy update exist.

## 6. Weekly operations

**Applicability: Reusable for Directory Platform**

- [ ] Complete a short authorized admin login/logout test.
- [ ] Verify sitemap HTTP status, MIME/content and canonical URLs.
- [ ] Verify robots.txt and its sitemap reference.
- [ ] Test canonical-host redirects and a query-string example.
- [ ] Verify the shared social image and representative page metadata.
- [ ] Test search, one filter, pagination and an external contact link.
- [ ] Review HTTP 404/500 trends and failed login patterns in available logs.
- [ ] Review database, log and backup growth.
- [ ] Confirm log rotation and retention work.
- [ ] Check public webroot for hidden configuration, database, backup, log,
      development manifest, temporary installer or diagnostics files.
- [ ] Review the editorial queue for aged pending items and source conflicts.
- [ ] Confirm the deployed commit still matches the operations record.

### PflegeIndex weekly profile

**Applicability: PflegeIndex only**

- `/sitemap.xml` must be valid XML and exclude admin/legal noindex URLs as
  defined by current SEO policy.
- `/robots.txt` must reference the HTTPS sitemap.
- `/assets/og-image.png` must return HTTP 200, `image/png`, without redirect.
- HTTP and `www` variants must make one 301 hop to
  `https://pflegeindex.com/<path>` without explicit `:443`.
- CSS and JavaScript URLs must use the currently deployed shared version.
- Laravel must create daily log files and remove application logs older than 30
  days.

## 7. Monthly operations

**Applicability: Reusable for Directory Platform**

- [ ] Restore one backup into an isolated non-production directory.
- [ ] Run database integrity and application-read checks against the restored
      copy; never test restoration over production.
- [ ] Confirm retention produces the approved daily/weekly/monthly backup set.
- [ ] Confirm at least one usable copy exists outside the hosting account when
      policy requires it.
- [ ] Review disk-capacity trend and estimate time to exhaustion.
- [ ] Review TLS certificate expiry and renewal responsibility.
- [ ] Review administrator accounts and remove unauthorized/stale access.
- [ ] Review application and hosting log retention against documented policy.
- [ ] Run dependency/security review in development or CI, not by updating
      production directly.
- [ ] Review editorial completeness and freshness metrics.
- [ ] Review unresolved incidents, repeated errors and overdue corrective work.
- [ ] Confirm operations, legal and hosting documentation still matches actual
      configuration.
- [ ] Test the operator's ability to reach the hosting escalation channel.

### PflegeIndex monthly profile

**Applicability: PflegeIndex only**

Minimum recommended backup retention from the current policy:

- 7 daily backups;
- 4 weekly backups;
- 6 monthly backups.

For the restored SQLite copy:

- confirm it opens read-only;
- run `PRAGMA integrity_check` and require `ok`;
- confirm expected core tables are accessible;
- compare key counts with backup metadata;
- do not connect the restored copy to the production document root.

Review at least these editorial metrics:

- percentage with valid phone;
- percentage with valid website;
- percentage with valid e-mail;
- percentage with reviewed source-backed description;
- percentage reviewed within the approved freshness window;
- pending contact suggestions and their age.

## 8. Production Health

**Applicability: Reusable for Directory Platform**

| Check | Healthy result | Frequency | Scope |
|---|---|---|---|
| Canonical homepage | HTTP 200 over HTTPS | Daily | Reusable for Directory Platform |
| Liveness | Exact expected body, no sensitive detail | Daily | Reusable for Directory Platform |
| Representative listing/detail | HTTP 200 and expected content | Daily | Reusable for Directory Platform |
| Sitemap | HTTP 200, valid XML, canonical public URLs | Weekly | Reusable for Directory Platform |
| Robots | HTTP 200, correct sitemap reference | Weekly | Reusable for Directory Platform |
| Canonical metadata | Matches the requested canonical public URL | Weekly/release | Reusable for Directory Platform |
| Redirects | One permanent hop to canonical host | Weekly/release | Reusable for Directory Platform |
| Social image | HTTP 200, expected image MIME, no redirect | Weekly/release | Reusable for Directory Platform |
| CSS/JS | HTTP 200 and version matches deployed release | Daily/release | Reusable for Directory Platform |
| TLS certificate | Valid chain, hostname and safe expiry margin | Monthly | Reusable for Directory Platform |
| Disk | Above operator-approved reserve | Daily | Reusable for Directory Platform |
| Backup | Recent, non-empty, protected and periodically restored | Daily/monthly | Reusable for Directory Platform |
| Application logs | No unexplained spike in severe errors | Daily | Reusable for Directory Platform |
| Hosting logs | No unexplained 404/500/security pattern | Weekly | Reusable for Directory Platform |
| Public exposure | No `.env`, database, log, backup or tool | Weekly/release | Reusable for Directory Platform |
| Admin | Authentication works; guests remain blocked | Weekly/release | Reusable for Directory Platform |

### PflegeIndex endpoint matrix

**Applicability: PflegeIndex only**

| Purpose | URL/expected result |
|---|---|
| Canonical origin | `https://pflegeindex.com` |
| Liveness | `/up` → 200, `text/plain`, exact `OK` |
| Homepage | `/` → 200 |
| Region | `/brandenburg.html` → 200 |
| Directory | `/pflegeheime.html` → 200 |
| Sitemap | `/sitemap.xml` → 200, XML |
| Robots | `/robots.txt` → 200, production sitemap URL |
| Social image | `/assets/og-image.png` → 200, `image/png`, no redirect |
| Legal | `/impressum.html`, `/datenschutz.html`, `/ueber-das-projekt.html` → 200 |
| Admin | `/admin/login` available; protected routes reject guests |

## 9. Backup and recovery standard

**Applicability: Reusable for Directory Platform**

- Back up before every migration, import or production data mutation.
- Put the application in maintenance mode when a consistent SQLite copy
  requires writes to stop.
- Store database, configuration backup and deployed commit metadata outside
  public webroot.
- Verify size/readability immediately after backup.
- Retain the pre-release backup through the approved observation period.
- Encrypt/protect backups according to their personal and administrative data.
- Test restoration monthly on an isolated copy.
- Never declare a backup verified solely because a file exists.
- Never overwrite the last known-good backup with an incident-state database.
- Preserve SQLite sidecar context when investigating WAL/journal incidents.

### PflegeIndex recovery source

**Applicability: PflegeIndex only**

The authoritative database path comes from production `DB_DATABASE`; never
assume it equals the repository's local path. The application core, SQLite,
`.env`, storage and backups must remain outside the public document root.

Use the selected deployment-package SQLite Scenario A/B and
`DEPLOYMENT_CHECKLIST.md`. Do not run `migrate:rollback` blindly against
production SQLite.

## 10. Editorial Operations

**Applicability: Reusable for Directory Platform**

```text
New or changed entry
→ source intake
→ contact discovery
→ verification
→ editorial draft
→ review
→ publication
→ scheduled re-verification
→ change history
```

### 10.1 New entries

**Applicability: Reusable for Directory Platform**

- accept only the approved source format;
- validate stable source identity and location;
- detect duplicates before import;
- perform dry run and backup before production mutation;
- keep official/base data separate from editorial additions;
- do not create a new public URL until identity and slug are approved;
- do not let an import overwrite protected manual data.

### 10.2 Contact discovery and verification

**Applicability: Reusable for Directory Platform**

- prefer the facility's official page over search snippets or reviews;
- store the exact source URL;
- verify name, address/location and facility identity;
- normalize without guessing missing digits or facts;
- parser output remains a suggestion;
- editor accepts or rejects field-level changes;
- accepted values receive source, checked date and protection;
- conflicts become `Needs review`, not automatic replacement.

### 10.3 Editorial draft and publication

**Applicability: Reusable for Directory Platform**

- draft remains private;
- factual claims require attributable sources;
- AI assistance never counts as a source and never auto-publishes;
- reviewer checks neutrality, uniqueness, rights and unsupported claims;
- publication is atomic and records actor, source, timestamp and reason;
- public URLs and SEO metadata change only through a separate approved task;
- rejected work does not change current public content.

### 10.4 Re-verification and history

**Applicability: Reusable for Directory Platform**

- schedule review by field type and approved freshness window;
- broken or changed sources create review tasks;
- stale data is not silently deleted;
- maintain previous/new value, actor, source, decision and timestamp;
- monitor coverage and overdue review metrics monthly.

### PflegeIndex editorial workflow

**Applicability: PflegeIndex only**

- Facility contacts use the existing contact-suggestion queue.
- `accepted` and `rejected` decisions require an authenticated administrator.
- Verified contacts must have at least one actual contact value.
- `contact_locked` protects manually confirmed contacts from ordinary imports.
- Description drafts require source URLs and a checked date before publication.
- Published descriptions remain distinguishable from LASV official base data.
- Follow the detailed target model in `EDITORIAL_MODEL.md` before extending the
  current schema with hours, media or notes.

## 11. Release Lifecycle

**Applicability: Reusable for Directory Platform**

```text
Development
→ Testing
→ GitHub
→ Deployment
→ Verification
→ Monitoring
→ Maintenance
```

### 11.1 Development

**Applicability: Reusable for Directory Platform**

- define scope and prohibited changes;
- inspect relevant routes, models, controllers, migrations and templates;
- preserve unrelated work;
- implement in a development branch/workspace;
- do not use production data as a disposable test fixture;
- update operational/legal documentation when behavior changes.

Exit condition: scoped diff is reviewable and contains no secrets or temporary
artifacts.

### 11.2 Testing

**Applicability: Reusable for Directory Platform**

- run narrow tests during implementation;
- run complete PHPUnit suite;
- run Pint in check mode;
- validate Composer strictly;
- run dependency audit where network permits;
- run `git diff --check`;
- verify migrations/imports on copies with rollback evidence when applicable;
- confirm public behavior and performance risk.

Exit condition: all required checks pass or an approved limitation is recorded.

### 11.3 GitHub

**Applicability: Reusable for Directory Platform**

- commit only reviewed files;
- use the approved message/tag/version;
- push the exact tested commit;
- record full hash;
- ensure CI/review status is known;
- build the release from that immutable commit, not a dirty workspace.

Exit condition: approved commit is reproducible and available to the deployment
operator.

### 11.4 Deployment

**Applicability: Reusable for Directory Platform**

- verify paths, environment and platform requirements;
- record currently deployed commit;
- enable maintenance mode when required;
- create and verify backup;
- deploy the complete matching core and public assets;
- run only approved migrations;
- rebuild caches after `.env` is verified;
- verify permissions and remove temporary deployment tools.

PflegeIndex deployments must execute `DEPLOYMENT_CHECKLIST.md` and respect the
FileZilla/shared-hosting split between private core and public document root.

Exit condition: deployment commands completed without unresolved error.

### 11.5 Verification

**Applicability: Reusable for Directory Platform**

- execute the exact live verification sheet;
- compare deployed hash and asset version;
- validate primary pages, admin, SEO, redirects and logs;
- choose explicit `approved` or `rollback required` result;
- never approve a partially deployed core/public-webroot pair.

PflegeIndex uses `RELEASE_VERIFICATION.md`.

### 11.6 Monitoring

**Applicability: Reusable for Directory Platform**

Perform an immediate check, then repeat during the approved observation window.
Until the operator defines another policy, use at least:

- immediately after go-live;
- approximately 30 minutes later;
- on the next operating day.

Watch errors, disk, database availability, assets, admin and user-critical page
types. Keep the previous artifact and backup protected throughout this window.

### 11.7 Maintenance

**Applicability: Reusable for Directory Platform**

- return to daily/weekly/monthly schedules;
- triage editorial queues;
- review dependency/security notices outside production;
- record incidents and preventive actions;
- retire backups only under the approved retention policy;
- keep documentation synchronized with actual hosting behavior.

## 12. Before every release

**Applicability: Reusable for Directory Platform**

- [ ] Scope, release owner and rollback trigger are agreed.
- [ ] Working tree is clean and exact commit recorded.
- [ ] Required automated and manual checks passed for that commit.
- [ ] No secrets, database, backup, logs or debug artifacts are in the release.
- [ ] Release artifact manifest/checksums match the commit.
- [ ] Database change and migration/import scope are explicit.
- [ ] Previous deployed hash is recorded.
- [ ] Verified backup location and restore responsibility are known.
- [ ] Environment changes are listed without secret values.
- [ ] Core/public asset versions belong to the same release.
- [ ] Legal/privacy documentation matches newly enabled external processing.
- [ ] Maintenance and hosting access are available.
- [ ] `RELEASE_VERIFICATION.md` or the catalog equivalent is ready.

### PflegeIndex release prerequisites

**Applicability: PflegeIndex only**

Use `DEPLOYMENT_CHECKLIST.md` without replacing its path, backup, SQLite,
Composer, migration, cache, permission or redirect checks with this summary.

## 13. After every release

**Applicability: Reusable for Directory Platform**

- [ ] Record deployed full commit hash and time.
- [ ] Confirm maintenance mode is disabled only after safe readiness checks.
- [ ] Execute the complete live verification sheet.
- [ ] Check fresh application and hosting logs.
- [ ] Confirm database integrity/readability if the release changed data/schema.
- [ ] Confirm asset version and cache headers.
- [ ] Confirm canonical, robots, sitemap and social image.
- [ ] Confirm administrator authentication and authorization.
- [ ] Remove one-time installers/diagnostics.
- [ ] Start and record the observation window.
- [ ] Preserve previous release artifact and backup.
- [ ] Announce approved/rollback-required result to the responsible role.

### PflegeIndex release sign-off

**Applicability: PflegeIndex only**

Complete `RELEASE_VERIFICATION.md`. Trust Layer, Content Layer and
`/assets/og-image.png` must all belong to the deployed release. A mismatch means
the deployment is incomplete and cannot be approved.

## 14. After rollback

**Applicability: Reusable for Directory Platform**

- [ ] Record failed and restored full commit hashes.
- [ ] Record the database backup used and its verification result.
- [ ] Preserve failed-release logs and artifact for analysis without exposing
      them publicly.
- [ ] Reinstall dependencies for the restored commit when required.
- [ ] Rebuild configuration, route and view caches.
- [ ] Verify database integrity and key counts.
- [ ] Execute the full release verification against the restored version.
- [ ] Confirm maintenance mode is disabled.
- [ ] Confirm no current asset is cached under the restored application's
      versioned URL incorrectly.
- [ ] Monitor immediately, after approximately 30 minutes and next operating
      day, or follow the approved observation policy.
- [ ] Open an incident record with timeline, root-cause owner and preventive
      action.
- [ ] Do not delete the incident-state database, backup or logs until review is
      complete and retention permits deletion.

### PflegeIndex rollback authority

**Applicability: PflegeIndex only**

Use the exact rollback procedure in `DEPLOYMENT_CHECKLIST.md`. Do not improvise
with `migrate:rollback` and do not overwrite production SQLite until the chosen
backup has been verified while the site remains in maintenance mode.

## 15. Incident severity and common first response

**Applicability: Reusable for Directory Platform**

| Severity | Examples | Initial action |
|---|---|---|
| SEV-1 | Site unavailable, suspected data loss/corruption, secret exposure, broad unauthorized access | Assign incident lead, preserve evidence, stop writes or enable maintenance mode |
| SEV-2 | Core search/detail/admin broken, sharp 500 increase, widespread 404, accidental noindex | Contain affected function, compare release/config, prepare rollback |
| SEV-3 | Isolated 404, stale editorial field, non-critical asset/problem | Record, reproduce and schedule controlled correction |

For every incident:

1. record time, symptoms and affected URLs;
2. preserve sanitized logs and current commit;
3. decide whether writes or public access must stop;
4. distinguish application, configuration, database, storage, DNS/TLS and
   provider causes;
5. prefer reversible containment;
6. verify recovery;
7. document root cause and prevention.

Suspected `.env`, APP_KEY, credential, session or database disclosure is a
security/privacy incident and must involve the responsible specialist before
reopening.

## 16. Incident playbook: site unavailable

**Applicability: Reusable for Directory Platform**

### Symptoms

- canonical homepage times out or returns 502/503;
- TLS/DNS error;
- liveness endpoint fails;
- maintenance page appears unexpectedly;
- all dynamic routes fail while static assets may still load.

### Checks

1. Check DNS, TLS and hosting status from an independent connection.
2. Check canonical host and liveness separately.
3. Record deployed commit and recent hosting/deployment change.
4. Check disk, PHP process/runtime, permissions and application logs.
5. Determine whether static files, Laravel boot or database-dependent pages
   fail.
6. Confirm maintenance-mode state without exposing its secret bypass.

### Temporary response

- keep or enable maintenance mode if responses are unsafe or inconsistent;
- rollback immediately when the outage follows a release and rollback criteria
  are met;
- escalate DNS/TLS/web-server failures to hosting;
- publish an approved static status response only if the hosting process
  supports it without exposing private files.

### Permanent fix

- correct the confirmed hosting, configuration, permission, capacity or release
  cause;
- rebuild caches only when configuration is verified;
- run full release verification;
- add a regression check or monitoring alert for the confirmed failure mode.

## 17. Incident playbook: database corruption

**Applicability: Reusable for Directory Platform**

### Symptoms

- SQLite reports malformed/corrupt database;
- pages fail with database I/O errors;
- integrity check is not `ok`;
- expected tables disappear or key counts change unexpectedly.

### Checks

1. Enable maintenance mode and stop admin/import writes.
2. Preserve the database and existing WAL/SHM/journal sidecars as incident
   evidence.
3. Confirm disk space, filesystem errors and permissions.
4. Run read-only integrity diagnostics on a copy where possible.
5. Identify the latest verified backup before the incident.
6. Compare timestamp, size, checksum and key counts.

### Temporary response

- keep writes stopped;
- do not run repair commands against the only copy;
- restore the latest verified pre-incident backup according to the approved
  recovery procedure;
- keep the corrupt copy protected for root-cause analysis.

### Permanent fix

- identify storage, interrupted-write, deployment or process cause;
- verify restored integrity and application behavior;
- account for data created after the restored backup;
- improve backup frequency/RPO or storage monitoring if required;
- document recovery evidence and prevention.

### PflegeIndex database note

**Applicability: PflegeIndex only**

Production SQLite location must come from `DB_DATABASE`, not the repository
default. Never expose or download it through public webroot. Follow the selected
SQLite Scenario A/B and backup-first procedure.

## 18. Incident playbook: deployment failed

**Applicability: Reusable for Directory Platform**

### Symptoms

- Composer, migration or cache build failed;
- blank/500 pages after upload;
- core and public assets show different release versions;
- admin login or primary page types fail verification.

### Checks

1. Keep maintenance mode active when safe.
2. Preserve command output and failed commit hash.
3. Compare deployed artifact/manifest/checksums with approved release.
4. Check platform requirements, vendor completeness, `.env`, permissions and
   cache state.
5. Determine whether database migration or data mutation completed.

### Temporary response

- stop deployment; do not patch production interactively;
- rollback to the previous immutable artifact/commit;
- restore database only when it changed and the backup is verified;
- rebuild dependencies and caches for the restored commit.

### Permanent fix

- reproduce in development/staging;
- correct package, migration, config or deployment procedure;
- add missing test/checksum/platform validation;
- create a new reviewed release rather than reusing a changed artifact.

## 19. Incident playbook: sitemap unavailable

**Applicability: Reusable for Directory Platform**

### Symptoms

- sitemap returns 404/500/HTML;
- invalid XML;
- wrong host or non-public URLs;
- robots references a missing sitemap.

### Checks

1. Record HTTP status, MIME type and a sanitized XML validation result.
2. Check sitemap route and route cache.
3. Verify production `APP_URL` and canonical host.
4. Check application logs and database-dependent generation.
5. Compare deployed commit/public core and last known-good response.

### Temporary response

- keep public pages stable; do not generate an unverified manual sitemap;
- restore the last known-good release if the failure followed deployment;
- correct an accidental cache/deployment mismatch only through the approved
  procedure.

### Permanent fix

- fix the confirmed route, configuration, query or deployment issue;
- add regression coverage for status, MIME, canonical host and exclusions;
- verify robots reference and resubmit/validate sitemap in webmaster tools.

### PflegeIndex sitemap note

**Applicability: PflegeIndex only**

Check `/sitemap.xml` and `/robots.txt`. Admin, health and intentionally excluded
legal/technical URLs must remain outside the sitemap according to current SEO
policy.

## 20. Incident playbook: new or widespread 404 responses

**Applicability: Reusable for Directory Platform**

### Symptoms

- previously valid listing/detail URLs return 404;
- internal links lead to missing pages;
- 404 count rises after import or deployment;
- assets return application HTML or 404.

### Checks

1. Separate content URL, route, asset and expected-not-found cases.
2. Compare affected URL with canonical, sitemap and internal links.
3. Check deployed route cache, source commit and asset package.
4. Check data identity, binding, slug and import/deletion history.
5. Confirm whether the URL was previously published.

### Temporary response

- use a 301 only when a definitive permanent replacement exists;
- rollback an import/release when it removed many valid URLs;
- do not redirect unrelated missing pages to the homepage;
- do not create placeholder content merely to return HTTP 200.

### Permanent fix

- restore correct data/routing or add an evidence-based permanent redirect;
- repair internal links and sitemap consistently;
- preserve published URL compatibility;
- add regression tests for the failure class.

## 21. Incident playbook: sharp increase in HTTP 500

**Applicability: Reusable for Directory Platform**

### Symptoms

- error-rate alert or repeated 500 log entries;
- specific page type fails;
- admin actions fail while reads work;
- failures correlate with deployment, disk or traffic change.

### Checks

1. Record rate, start time, routes and correlation ID if available.
2. Inspect sanitized Laravel and hosting error logs.
3. Check disk, permissions, SQLite access, cache and PHP version/extensions.
4. Compare the start time with deploy/import/config/editorial operations.
5. Reproduce one safe request; avoid creating load with repeated probes.

### Temporary response

- disable/stop the affected write process;
- enable maintenance mode for broad unsafe failure;
- rollback when release-related;
- preserve logs before rotation removes evidence.

### Permanent fix

- correct the confirmed code/config/data/capacity cause in a reviewed release;
- add regression and operational monitoring;
- verify all public page types and admin after recovery;
- document the root cause and time window.

## 22. Incident playbook: Google indexing declines or stops

**Applicability: Reusable for Directory Platform**

### Symptoms

- indexed page count falls sharply;
- valid URLs become excluded;
- Search Console reports robots, noindex, canonical, redirect or server errors;
- sitemap processing fails.

### Checks

1. Confirm the issue with Search Console and raw HTTP responses, not only a
   `site:` query.
2. Check manual actions/security notifications.
3. Inspect robots, meta robots/X-Robots-Tag, canonical, status and redirect chain
   on representative page types.
4. Validate sitemap host, freshness and public URL inclusion.
5. Check 404/500 trends, availability and recent SEO/deployment changes.
6. Compare Google-selected and declared canonical where available.
7. Distinguish technical exclusion from normal crawling/indexing delay.

### Temporary response

- do not make mass SEO changes without identifying the cause;
- revert an accidental global noindex/robots/canonical change through an
  approved release;
- restore site availability or sitemap first when they are the confirmed cause;
- keep analytics activation separate: GA4/Clarity are not required for Google
  indexing.

### Permanent fix

- correct the confirmed technical cause with tests for every affected page
  type;
- deploy and complete release verification;
- resubmit sitemap or request validation where appropriate;
- monitor coverage and affected URLs through the next crawl cycle;
- document whether the event was technical, content-quality or external.

### PflegeIndex indexing profile

**Applicability: PflegeIndex only**

Sample Home, Brandenburg, District, City and Facility URLs. Search/filter pages
must retain their intended noindex behavior while normal catalog pages remain
indexable. Follow `ANALYTICS_SETUP.md` for Search Console verification; do not
enable GA4 or Clarity to diagnose indexing.

## 23. Platform Perspective

**Applicability: Reusable for Directory Platform**

The reusable operations contract for every catalog is:

- canonical origin and liveness definition;
- representative listing/detail page set;
- environment and secret-handling rules;
- database backup/restore procedure;
- release artifact and commit identity;
- cache/asset versioning strategy;
- public exposure checks;
- sitemap/robots/canonical monitoring;
- admin/authentication verification where administration exists;
- editorial draft/review/publish/recheck workflow;
- incident severity, evidence and rollback criteria;
- daily/weekly/monthly schedule.

Each project supplies an operations profile containing:

- project name and canonical host;
- public endpoint matrix;
- health response contract;
- database engine/path resolution method;
- required PHP/extensions;
- release-package layout;
- public/private filesystem mapping;
- log location/retention;
- backup retention and restore commands;
- sample page URLs;
- project-specific editorial source policy;
- legal/privacy requirements;
- hosting escalation route.

Directory Platform must not hard-code PflegeIndex routes, LASV rules,
Brandenburg geography, SQLite paths or `pflegeindex.com` into reusable tooling.

## 24. Handbook maintenance

**Applicability: Reusable for Directory Platform**

Review this handbook:

- after every SEV-1/SEV-2 incident;
- after a deployment or rollback procedure changes;
- after database, hosting, logging, analytics or backup architecture changes;
- before launching a second Directory Platform catalog;
- at least quarterly even when no incident occurred.

Changes require documentation review and must not silently change production
behavior. Release-specific facts belong in a dated release/status document;
evergreen operating rules belong here or in the authoritative execution
checklists.

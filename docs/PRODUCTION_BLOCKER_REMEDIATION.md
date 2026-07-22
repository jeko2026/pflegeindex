# Production Blocker Remediation

**Sprint:** 3.5.1

**Date:** 2026-07-22

**Application artifact source:** `024ac70eb9eb5bbff6d5965f7093d9f94e45355a`

**Status:** **CONDITIONAL PASS — repository and packaging blockers resolved;
explicit operator/hosting confirmations remain**

## 1. Initial State

`main` started three commits ahead of `origin/main`:

| Commit | Message |
| --- | --- |
| `7b806a260a054e2ac7b1109f3f51cdd9ecc00443` | `feat(geocore): apply approved city mappings` |
| `c3169b9622c5e740c813d99594091b5ba1beea6d` | `docs(geocore): final validation audit` |
| `024ac70eb9eb5bbff6d5965f7093d9f94e45355a` | `docs: production readiness certification` |

The worktree contained one untracked audit, two Pint deviations, stale release
documents and generated archives from older commits. The public server was
known to serve an older release with a missing Open Graph image, legacy HTML
health endpoint and a two-hop HTTP `www` redirect.

No production data, local SQLite content, migration or GeoCore mapping was
changed during remediation.

## 2. Completed Commit Push

The three approved commits above were pushed normally to `origin/main` before
remediation. No force push, rebase, amend or history rewrite was used. After the
push, local `main` and `origin/main` both resolved to
`024ac70eb9eb5bbff6d5965f7093d9f94e45355a`.

The remediation commit created by this sprint is intentionally not pushed.

## 3. Repository Hygiene

`docs/audits/PRODUCTION_READINESS_AUDIT.md` was classified as valid historical
project documentation. It records the 2026-07-21 baseline at `beed552…` that
triggered the hardening work. Deleting or ignoring it would remove useful
evidence. It is now explicitly labelled as a historical snapshot and includes
a current remediation-status section so its former 77-city, 122-test and
unresolved P1 figures cannot be mistaken for the current release state.

Tracked files and package contents were scanned for credentials, keys, tokens,
`.env`, SQLite, logs, backups and Git metadata. No tracked secret or forbidden
release file was found. The real local `.env` was not read or changed.

## 4. Pint Remediation

Pint made formatting-only changes:

- `app/Http/Controllers/HomeController.php`: normalized array alignment;
- ignored `deployment/server-diagnostics.php`: normalized PHP syntax/style.

Both files pass `php -l`. Homepage behavior, queries and returned view data are
unchanged. The ignored diagnostic script is not included in Git or release
archives.

`vendor/bin/pint --test` now passes.

## 5. Canonical Redirect Remediation

Laravel middleware and the production Apache `.htaccess` already redirect all
three non-canonical scheme/host variants directly to
`https://pflegeindex.com$request_uri`. Existing feature coverage was clarified
to document that exact `Location` assertions prove:

- one permanent 301 response;
- path and query-string preservation;
- no intermediate HTTPS `www` hop;
- no explicit `:443` default port;
- no loop on canonical HTTPS requests.

Because production uses nginx and does not process `.htaccess`,
`deployment/nginx-canonical-redirect.conf` now provides the exact HTTP and
HTTPS-`www` vhost rules. Applying them remains a hosting/control-panel action
during the approved deployment window; production was not changed in this
sprint.

The focused redirect test passed: 1 test, 11 assertions.

## 6. Release Documentation

The following documents now describe the current release candidate:

- `docs/RELEASE_NOTES.md`;
- `docs/PROJECT_STATUS.md`;
- `docs/KNOWN_LIMITATIONS.md`;
- `docs/DEPLOYMENT_CHECKLIST.md`;
- `docs/OPERATIONS.md`;
- `docs/PRODUCTION_PACKAGE.md`;
- `docs/PRODUCTION_CERTIFICATION.md` (marked as the pre-remediation snapshot);
- `README.md`.

They record 257 cities, 255 mapped cities, two unresolved cities, 1,557
facilities, 1,552 facilities covered by GeoCore, 18 district pages, the final
GeoCore validation, the 202-test/3,070-assertion pre-remediation baseline and
the conditional certification state. They do not claim that deployment, server
backup or final GO approval has occurred.

## 7. Legal and Hosting Facts

`docs/LEGAL_HOSTING_FACTS_CHECKLIST.md` separates confirmed repository facts
from operator/provider unknowns. It covers provider identity/address, server
location, DPA, logs, mailbox processing/retention, public contact behavior,
external resources, analytics/cookies, SQLite/backups, monitoring, CDN/proxy
and subprocessors.

No hosting or mailbox fact was invented and Datenschutzerklärung was not
changed. Final legal copy remains a separate owner-approved task.

## 8. Old Artifact Deprecation

All old output was generated and ignored, so it was deleted rather than
committed. It can be reproduced from Git if needed.

| Artifact/build | Created (UTC) | Source commit | SHA-256/status | Action |
| --- | --- | --- | --- | --- |
| incomplete directory `27d008c0` | 2026-07-21 06:47 | `27d008c0ec97e2ba2f5fadbdbef4d2bbf17f9c1c` | dependency build blocked; no ZIP | Deleted |
| directory `5d5ea489` | 2026-07-21 09:21 | `5d5ea48989358370d522c917a5316828d38a05c4` | complete but obsolete | Deleted |
| `pflegeindex-private-core-5d5ea489.zip` | 2026-07-21 09:27 | `5d5ea489…` | `122884BEECB1D3D9100429E58858C302D79116EB84A67DCC05B898580527C28E` | Deleted |
| `pflegeindex-public-webroot-5d5ea489.zip` | 2026-07-21 09:27 | `5d5ea489…` | `0D3F14A3566FBF09EE2A1136FEF582A0F923148FAA13656D58BBB0310162E91C` | Deleted |
| `pflegeindex-manifest-5d5ea489.zip` | 2026-07-21 09:27 | `5d5ea489…` | `76102F7E41C338984400326D5BB42F2E941A83E94635DFD4E3F8C52187E22FB8` | Deleted |
| residual `.self-check-*` directory | 2026-07-21 | temporary | no manifest | Deleted |

## 9. Packaging Defect Found and Fixed

The first new build exposed a packaging-order defect: `build-info.json` inside
the manifest ZIP said `archivesCreated: false` because the manifest was zipped
before final metadata and checksum regeneration. That candidate and all its
archives were deleted.

`scripts/build-production-package.ps1` now creates private/public ZIPs, updates
`build-info.json`, regenerates and verifies checksums, and only then creates the
manifest ZIP. The corrected extracted manifest reports
`archivesCreated: true`.

The builder self-check passed all 12 checks, including a real production-only
Composer installation.

## 10. Current Release Candidate Artifact

Only one candidate set remains under `build/production/`. It was exported with
`git archive` from the clean, already-pushed application commit
`024ac70eb9eb5bbff6d5965f7093d9f94e45355a`; uncommitted remediation documents
and local files could not enter the payload.

Build timestamp: `2026-07-22T07:26:48.7124386Z`

PHP target/build runtime: `8.2.27`

Laravel: `v12.64.0`

Production dependency status: `complete`

| Archive | Intended target | Bytes | SHA-256 |
| --- | --- | ---: | --- |
| `pflegeindex-private-core-024ac70e.zip` | New private application directory outside webroot | 7,782,960 | `9FD3F6CD84F81DC9C8278057BDBCDBE36DA045019BADF27DA0CC5F6FDD6A53B3` |
| `pflegeindex-public-webroot-024ac70e.zip` | Domain document root | 638,070 | `4CA5A24A3A8D760248F384F3FA7F96FC2B4FADD276629CEBCC327F52DAAD1560` |
| `pflegeindex-manifest-024ac70e.zip` | Protected local release record; never webroot | 302,981 | `5D529F5371BED6FC42E4DA718AC640B8B4C97B8DE2D626B9226EE1005AF52E5D` |

Extracted top-level structure:

```text
private-core/
├── app, bootstrap, config, database/migrations, resources, routes, storage
├── vendor
├── artisan
├── composer.json
└── composer.lock

public-webroot/
├── assets/
├── .htaccess
├── index.php
├── favicon.svg
├── logo.svg
└── logo-light.svg

manifest/
├── RELEASE_MANIFEST.md
├── build-info.json
├── files.sha256
├── .env.production.template
└── DATABASE_DEPLOYMENT_DECISION.md
```

## 11. Extracted Artifact Verification

All three ZIPs extracted successfully into a temporary verified child of
`build/production`; the directory was removed afterwards.

| Check | Result |
| --- | --- |
| Manifest source commit | PASS — exact `024ac70…` |
| `archivesCreated` | PASS — `true` inside extracted manifest ZIP |
| Composer production dependencies | PASS — optimized `vendor/autoload.php` present |
| Development PHPUnit vendor | PASS — absent |
| Current routes | PASS — `private-core/routes/web.php` present |
| Current health implementation | PASS — plain-text `OK`; no Bunny Fonts/jsDelivr references |
| OG metadata URL | PASS — `https://pflegeindex.com/assets/og-image.png` |
| OG public asset | PASS — `assets/og-image.png`, PNG, 1254×1254, 624,884 bytes |
| Production `.htaccess` | PASS — direct canonical host rules and hidden-file protection present |
| `.env`, SQLite, Laravel log, Git, tests, node_modules | PASS — 0 forbidden files |
| Package checksums | PASS — builder verification succeeded |
| Temporary extraction cleanup | PASS |

The public front controller intentionally retains `__PRIVATE_CORE_PATH__`; the
operator must replace it with the new private-core server path in a protected
deployment copy. SQLite and the real `.env` are intentionally absent.

## 12. Verification Results

| Check | Result |
| --- | --- |
| Focused canonical redirect test | PASS — 1 test, 11 assertions |
| `vendor/bin/pint --test` | PASS |
| Builder self-check | PASS — 12/12 |
| Artifact extraction/content scan | PASS |
| `php artisan test` | PASS — 202 tests, 3,070 assertions |
| `composer validate --strict` | PASS |
| `composer audit --locked --no-interaction` | PASS — no known security vulnerability advisories |
| `git diff --check` | PASS after the report's final formatting check |

## 13. Remaining Manual and Legal Conditions

The following are not repository defects and cannot be completed without
production authority or operator facts:

1. complete and approve `LEGAL_HOSTING_FACTS_CHECKLIST.md`;
2. update/finalize Datenschutzerklärung only from confirmed facts;
3. apply the nginx canonical redirect and default-vhost rules in the hosting
   panel, then verify live one-hop responses;
4. create and verify the production SQLite and `.env` backup and rollback
   reference;
5. select the production database scenario and verify paths, permissions,
   migrations, caches and disk space;
6. deploy the approved package, replace the private-core placeholder, rebuild
   Laravel caches and verify the exact manifest;
7. confirm provider monitoring, log retention, backup automation and a restore
   test;
8. run the complete post-deployment checklist and confirm live OG image and
   plain-text `/up`.

## 14. Recommendation

**CONDITIONAL PASS — ready for final release verification.**

Repository hygiene, Pint, reproducible packaging, artifact content and
documentation blockers are resolved. No technical application blocker remains
for entering the controlled backup/deployment verification stage. Final GO is
still prohibited until the explicitly listed owner/hosting/legal confirmations
and post-deployment checks pass.

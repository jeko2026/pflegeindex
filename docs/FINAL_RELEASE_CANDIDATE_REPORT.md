# PflegeIndex Final Release Candidate Report

**Prepared:** 2026-07-22

**Status:** **CONDITIONAL GO — only owner and hosting confirmations remain**

**Deployment performed:** **No**

**Production database modified:** **No**

## Source and Git State

The final release candidate was exported from the exact committed source:

```text
fd01a71fb5084c22aea83ba6d50bcc00b7769f72
chore: resolve production release blockers
```

The remediation commit was pushed with a normal fast-forward push:

```text
024ac70..fd01a71  main -> main
```

Immediately before the build, local `main` and `origin/main` both resolved to
the full source commit above and `git status --porcelain` was empty. The build
uses `git archive`, so ignored or uncommitted files cannot enter the payload.

The previous `024ac70e` candidate directory and its three ZIP archives were
removed from the active artifact area before this build. They must not be used
for deployment.

## Build Identity

The current builder uses an eight-character short commit, so the actual release
identifier is `fd01a71f`. This contains the requested unambiguous `fd01a71`
prefix and was not manually renamed.

- Build date (UTC): `2026-07-22T07:50:34.5701865Z`
- Build PHP: `8.2.27`
- Target PHP: `8.2.27`
- Laravel: `v12.64.0`
- Composer dependency build: `complete`
- Production dependencies: 76 installed from `composer.lock`
- `composer check-platform-reqs --no-dev`: PASS
- Forbidden-file scan: PASS
- Source working tree after build: unchanged

## Final Archives

| Archive | Bytes | SHA-256 | Deployment target |
| --- | ---: | --- | --- |
| `pflegeindex-private-core-fd01a71f.zip` | 7,782,961 | `A515FED07D130A014B412F30EEBED6A13D8418FE8ADD709F6EC897BB4C9A05A4` | New private release directory outside the public webroot |
| `pflegeindex-public-webroot-fd01a71f.zip` | 638,070 | `428B0D0AC6B339D01D8775D324F959350E69508E6D38FEA6CE7D6538992B68EF` | Domain document root after front-controller path replacement |
| `pflegeindex-manifest-fd01a71f.zip` | 302,983 | `64D926B6CF7A81BED13794246BFF613E3C786E353B5A62BEBD1D666DD7A075E9` | Keep with the private release record; never upload to webroot |

The ZIP files are ignored build artifacts and are not added to Git.

## Manifest Review

The generated manifest records:

- full and short source commit;
- exact UTC build time;
- PHP target/build version and Laravel version;
- `composer.lock` SHA-256;
- private/public file counts and payload bytes;
- exact package composition and excluded categories;
- complete Composer and platform-check status;
- passed forbidden-file scan;
- `archivesCreated: true` after final archive creation;
- database exclusion and required front-controller replacement;
- final payload file checksums in `files.sha256`.

Final outer ZIP sizes, SHA-256 values, test status, certification status and
the explicit `deployment performed: No` declaration are recorded in this
report. An archive cannot contain its own final SHA-256 without changing that
same SHA recursively; therefore the outer archive hashes are intentionally
kept in this post-build release record rather than inserted into the manifest
ZIP. The archives were not modified after these hashes were calculated.

## Independent Extraction Verification

All three ZIP archives were independently extracted into a new temporary
directory and inspected. The temporary directory was removed afterward.

| Check | Result |
| --- | --- |
| Source commit in extracted manifest | PASS — exact `fd01a71fb5084c22aea83ba6d50bcc00b7769f72` |
| Private files | PASS — 6,300 files |
| Public files | PASS — 9 files |
| Manifest files | PASS — 5 files |
| Application code, routes, bootstrap and config | PASS |
| Migrations | PASS — 11 migration files |
| Production `vendor/autoload.php` | PASS |
| Writable storage skeleton | PASS |
| `.env`, production SQLite, backups, logs, tests, Git and Node files | PASS — none present |
| Public `index.php` and `.htaccess` at archive root | PASS |
| Current CSS and JavaScript | PASS — local `assets/styles.css` and `assets/app.js` |
| OG image | PASS — local PNG, 1,254 × 1,254, 624,884 bytes |
| Favicon and logos | PASS |
| `/up` implementation | PASS — plain `OK`, no diagnostics or external resources |
| Manifest, build info and payload checksums | PASS |
| Independent payload SHA-256 verification | PASS — 6,313/6,313 entries, zero mismatches |
| Temporary verification directory removed | PASS |

The packaged `index.php` deliberately still contains
`__PRIVATE_CORE_PATH__`. It returns 503 until an administrator replaces that
placeholder in a protected deployment copy with the absolute production
private-core path.

## Reproducibility

The builder exports the named Git commit instead of the working directory,
installs dependencies from `composer.lock`, validates platform requirements,
scans forbidden files and regenerates output only below `build/production`.
Packaging self-check passed **12/12**, including a real Composer production
installation, dirty-worktree independence and repeat-build cleanup.

ZIP binary hashes may differ on a later rebuild because ZIP metadata and build
timestamps change. Reproducibility is defined by the same source commit,
dependency lock, package composition and internal payload checksums, not by an
expectation that separately timestamped ZIP containers are byte-identical.

## Automated Quality Results

| Check | Result |
| --- | --- |
| `php artisan test` | PASS — 202 tests, 3,070 assertions |
| `composer validate --strict` | PASS |
| `composer audit --locked --no-interaction` | PASS — no security advisories |
| `vendor/bin/pint --test` | PASS |
| `git diff --check` before documentation | PASS |
| Packaging self-check | PASS — 12/12 |
| Independent archive extraction | PASS |

The local SQLite SHA-256 remained
`5F37FB8A12743DEF3A41C96A55D415D164A91E009DE6A20D5E33423AD34A4423`;
no migration, import or database write was performed.

## nginx Canonical Redirect Review

`deployment/nginx-canonical-redirect.conf` defines:

- HTTP `pflegeindex.com` and `www.pflegeindex.com` → one direct 301 to the
  exact HTTPS non-www host;
- HTTPS `www.pflegeindex.com` → one direct 301 to the exact HTTPS non-www host;
- `$request_uri` preservation, including path and query string;
- the canonical HTTPS `pflegeindex.com` vhost as the serving vhost without a
  further host-normalization redirect.

Automated Laravel regression tests assert exact locations, one-hop behavior,
query preservation and no explicit `:443`. The repository configuration has
**not** been applied to production. The hosting administrator must validate,
apply/reload and live-test it using `docs/FINAL_DEPLOYMENT_RUNBOOK.md`.

## Remaining Owner and Hosting Actions

1. Answer `docs/OWNER_LEGAL_HOSTING_QUESTIONS.md` using provider evidence.
2. Update the factual checklist and obtain final legal approval of the
   Datenschutzerklärung and operator details.
3. Confirm private/public/database/backup paths and available disk space.
4. Create and verify the production backup and select SQLite Scenario A or B.
5. Upload the private core and public webroot following the runbook.
6. Preserve `APP_KEY`; verify production `.env` without exposing it.
7. Replace the front-controller placeholder only in the protected deployment
   copy.
8. Apply and live-test the nginx canonical redirects.
9. Run migrations, cache build, smoke/security/SEO checks and release
   acceptance exactly as documented.
10. Monitor the release and confirm the first post-release backup.

## Legal Questions Still Open

The repository does not establish the provider legal entity/address, server
country, AV-Vertrag/subprocessors, provider log fields/retention, mailbox
provider/location/retention/forwarding, real backup locations/retention/restore
evidence, provider monitoring, or provider-level CDN/tracking. These facts must
not be guessed. They are listed in
`docs/OWNER_LEGAL_HOSTING_QUESTIONS.md` and
`docs/LEGAL_HOSTING_FACTS_CHECKLIST.md`.

## Release Decision

**Technical release candidate readiness: 97%.**

**Final status: CONDITIONAL GO — only owner and hosting confirmations remain.**

No unresolved application-code, dependency, package-content or automated-test
blocker was found. Public deployment remains prohibited until owner/legal facts,
production backup, server paths/permissions, nginx application and the live
acceptance checklist are confirmed. This sprint did not perform deployment.

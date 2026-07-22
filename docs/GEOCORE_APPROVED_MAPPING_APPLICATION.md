# GeoCore Approved Mapping Application

## Status

**PASS**

Application date: 2026-07-22

The 75 approved GeoCore city mappings were applied to the working local SQLite database after a successful preflight and creation of a verified SQLite backup. The two `REVIEW` records were not applied. Production was not accessed or changed.

## Git synchronization

The prerequisite dry-run commit was pushed before the database operation:

```text
4c212468b990cf19fd4c1ed7bd617cb0e7b00e96
feat(geocore): add approved mapping dry run
```

`HEAD` and `origin/main` matched at this commit before implementation of the application mode.

## Preflight

| Check | Result |
| --- | --- |
| Configured database | Expected file-backed local SQLite |
| Database readable and writable | PASS |
| SQLite integrity | `ok` |
| Migration files and migration table | 11/11, all current |
| Cities | 257 |
| Mapped cities | 180 |
| Unresolved cities | 77 |
| Facilities | 1,557 |
| Facilities on district pages | 1,158 |
| Approved CSV | 75 `APPROVED`, 2 `REVIEW`, 0 `SKIP` |
| Approved mapping dry run | PASS |
| Unexpected working tree changes | None; only sprint implementation files and the pre-existing untracked audit were present |

The working database SHA-256 before application was:

```text
d5e5c029669fd091d1a3cd99c598f9afcce48ecfe376744c2d201f1365c781c8
```

## Backup

Safe relative backup name:

```text
../database-backups/pflegeindex-before-geocore-approved-20260722-075801-765de238.sqlite
```

The backup directory is outside the Laravel repository and public webroot. The backup was created with SQLite `VACUUM INTO`; no existing file was overwritten. It was not added to Git.

| Check | Result |
| --- | --- |
| Backup exists and is non-empty | PASS, 3,137,536 bytes |
| SQLite integrity | `ok` |
| Cities | 257 |
| Mapped cities | 180 |
| Facilities | 1,557 |
| Districts | 18 |
| Municipalities | 413 |
| Backup SHA-256 | `95ade9c8c47cd49f1792346f6f427f4ab1a233a6856df80e1f5fe8603cc4d9b7` |

The backup hash differs from the source file hash because `VACUUM INTO` creates a compact, consistent SQLite image. Integrity and all key logical counts match the pre-application source.

## Controlled application

Command:

```text
php artisan geocore:apply-approved-mapping --apply
```

Safety controls:

- exactly one explicit mode (`--dry-run` or `--apply`) is required;
- production execution is blocked;
- only a readable and writable file-backed SQLite database is accepted;
- active WAL or journal sidecars block application;
- integrity, migrations, CSV structure, city records, municipality AGS, district relations, facility counts, and expected initial state are validated before backup or mutation;
- the backup directory must already exist, be writable, and be outside the public webroot;
- the backup is verified before the mapping transaction starts;
- only `APPROVED` rows are processed;
- conflicting mappings are rejected and never overwritten;
- all mapping writes and result validations use one database transaction;
- municipalities, districts, city names/slugs, facility records, routes, and sitemap code are not changed;
- any failure after mutation starts triggers restoration from the verified backup and revalidation of its hash, integrity, and key counts.

Command result:

| Metric | Result |
| --- | ---: |
| Applied | 75 |
| Already mapped | 0 |
| Skipped `REVIEW` | 2 |
| Conflicts | 0 |
| Errors | 0 |
| Rollback | Not required |

The two skipped records remain:

- city 99, Hennickendorf — 4 facilities;
- city 190, Reichenberg, Märkische Heide — 1 facility.

## Database result

| Metric | Before | After | Delta |
| --- | ---: | ---: | ---: |
| Cities | 257 | 257 | 0 |
| Mapped cities | 180 | 255 | +75 |
| Unresolved cities | 77 | 2 | -75 |
| `unmatched` cities | 0 | 0 | 0 |
| Facilities | 1,557 | 1,557 | 0 |
| Facilities on district pages | 1,158 | 1,552 | +394 |
| Facilities without district | 399 | 5 | -394 |
| District pages | 18 | 18 | 0 |
| Empty district pages | 1 | 0 | -1 |

Post-application SQLite integrity returned `ok`. Approved city assignments match the CSV, both `REVIEW` city IDs remain unresolved, unknown municipality IDs are 0, municipality-to-district relations match, and the district join returns 1,552 facilities and 1,552 distinct facility IDs.

Working database SHA-256 after application:

```text
5f37fb8a12743def3a41c96a55d415d164a91e009de6a20d5e33423ad34a4423
```

## District delta summary

| AGS | District | Cities delta | Facilities delta |
| --- | --- | ---: | ---: |
| 12052 | Cottbus, Stadt | +1 | +72 |
| 12060 | Barnim | +4 | +31 |
| 12061 | Dahme-Spreewald | +4 | +15 |
| 12062 | Elbe-Elster | +7 | +20 |
| 12063 | Havelland | +1 | +3 |
| 12064 | Märkisch-Oderland | +8 | +37 |
| 12065 | Oberhavel | +5 | +13 |
| 12066 | Oberspreewald-Lausitz | +8 | +53 |
| 12067 | Oder-Spree | +4 | +20 |
| 12068 | Ostprignitz-Ruppin | +8 | +27 |
| 12069 | Potsdam-Mittelmark | +7 | +20 |
| 12070 | Prignitz | +1 | +1 |
| 12071 | Spree-Neiße | +11 | +64 |
| 12072 | Teltow-Fläming | +4 | +13 |
| 12073 | Uckermark | +2 | +5 |
| **Total** | **15 affected districts** | **+75** | **+394** |

No district page was created or removed. Cottbus, Stadt changed from 0 to 72 facilities and is no longer empty.

## Public behavior verification

The current Laravel application was booted against the updated local SQLite and checked without a web server.

| Page | Result |
| --- | --- |
| Brandenburg | HTTP 200, JSON-LD present |
| Newly mapped Cottbus city | HTTP 200, breadcrumb/canonical/JSON-LD present, district link present |
| Affected Barnim district | HTTP 200, breadcrumb/canonical/JSON-LD present |
| Previously empty Cottbus district | HTTP 200, 72 facilities, breadcrumb/canonical/JSON-LD present |
| Cottbus district page 2 | HTTP 200 with page-specific canonical |
| Facility from newly mapped Cottbus | HTTP 200, breadcrumb/canonical/JSON-LD present |

Existing ordering and pagination tests remain successful. Database joins show no duplicate facility cards across district pages.

Local canonical URLs use the configured local host (`http://localhost`). Canonical generation code and production configuration were not changed by this sprint; existing production-host tests remain in the full suite.

## Sitemap verification

| Check | Backup state | Applied state |
| --- | ---: | ---: |
| HTTP status | 200 | 200 |
| `<loc>` entries | 1,899 | 1,899 |
| `/up` present | No | No |
| Cottbus city URL present | Yes | Yes |
| Cottbus district URL present | Yes | Yes |

No city, facility, or district URL was added or removed. The dry-run URL inventory comparison and the rendered sitemap counts both remained stable.

## Idempotency

A second execution of the real application command completed successfully:

| Metric | Result |
| --- | ---: |
| Applied | 0 |
| Already mapped | 75 |
| Skipped `REVIEW` | 2 |
| Conflicts | 0 |
| New backup created | No |
| Database SHA-256 changed | No |

The command distinguishes the expected initial state, expected completed state, and unknown/intermediate state. Unknown states are rejected without an override or database mutation.

## Rollback status

**Not required.**

The real application and every post-check passed. Automated tests separately forced a post-check failure and confirmed restoration from the verified backup, original counts, and SQLite integrity.

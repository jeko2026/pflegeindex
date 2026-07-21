# Directory Platform — Project Status

## Version

`PflegeIndex v1.0 Release Candidate (v1.0.0-rc.1)`

## Current phase

Release Candidate — Production preparation

## Current iteration

Release baseline and documentation freeze

## Current project

PflegeIndex

## Implemented platform foundation

- GeoCore database hierarchy: Country → State → District → Municipality.
- Official Brandenburg GeoCore staging import and verified PflegeIndex city mapping.
- DirectoryCore contracts, immutable value objects and read models.
- PflegeIndex `EntryRepository` adapter and presentation adapter.
- `/pflegeheime.html` uses `ListEntries`.
- `/brandenburg/{city}.html` uses `ListEntries`.
- District and Brandenburg region facility listings use `ListEntries`.
- Typed City, District and State location scopes.
- Allowlist dependency protection for `app/Platform`.
- DirectoryCore is independent of Laravel.
- PflegeIndex and the minimal FuneralIndex proof adapter implement the same `EntryRepository` Platform contract.

## Multi-project architecture proof

- `FuneralEntryRepository` resolves through the Laravel container and executes the existing `ListEntries` use case.
- The proof adapter contains configuration only and has no models, persistence, routes, pages or business rules.
- No global `EntryRepository` binding is registered, so PflegeIndex production resolution is unchanged.
- DirectoryCore and its public API required no changes for the second adapter.

## Platform API freeze

Status: **frozen for the PflegeIndex v1.0 Release Candidate**. Breaking changes
require an explicit future major Platform version.

The supported Platform API consists of:

- the `EntryRepository` contract;
- the `ListEntries` use case;
- `ListingCriteria`, `ListingResult` and `EntrySummary` read models;
- `EntryIdentifier`, `EntrySort`, `LocationScope`, `LocationScopeType` and
  `PaginationOptions` domain types.

All other implementation state in DirectoryCore is private. No currently public
class can be made internal without removing a type required by a repository
adapter or a Platform consumer.

The raw `LocationScope` constructor remains supported in v1.0 together with its
typed factories. Its dual use as listing criteria and entry location metadata
may be revisited only in a future major API version. Dormant convenience
operations and enum cases remain supported for compatibility.

## Tests

97 passed, 2365 assertions.

## Known architecture debt

- GeoCore Eloquent models are not physically separated under `app/Platform`.
- `GeoMunicipality::cities()` creates a reverse dependency on the PflegeIndex `City` model.
- Unmapped legacy cities still require a guarded State scope fallback.
- Region city and type aggregates still use legacy `state_slug` queries.
- Shared SEO and presentation abstractions have not been introduced.
- `LocationScope` identifiers are intentionally adapter-defined and currently
  use different official or public identifiers for City, District and State.
- The FuneralIndex adapter is an architecture proof, not a production-ready
  second directory.

## Production Readiness

| Area | Status | Assessment |
| --- | --- | --- |
| Architecture | Ready | Dependency direction is protected by allowlist architecture tests. |
| Tests | Ready | Architecture and application coverage form the current release baseline. |
| Documentation | Ready | Release notes, changelog, known limitations and version status form the RC baseline. |
| Platform API | Frozen for RC | The supported public surface, including the `LocationScope` constructor, is recorded. |
| SEO Foundation | Ready | Existing public SEO behavior remains covered and unchanged. |
| DirectoryCore | Ready | Framework-independent API is used by all public PflegeIndex listings. |
| Multi-project support | Proven | A second adapter uses the unchanged Platform API; production project bootstrapping is intentionally out of scope. |

Not yet ready for production release:

- production-only Composer dependencies and PHP 8.2.27 platform verification;
- explicit selection of SQLite deployment Scenario A or B;
- production deployment, backup and rollback checklist verification.

The minimal Impressum, Datenschutz and project-information pages are prepared
for release. A reproducible split-package builder is available, but it remains
blocked from producing final ZIP archives until PHP CLI and Composer are
available locally.

## Next goal

Complete the production deployment, backup, rollback and post-deployment
verification checklist without expanding DirectoryCore or changing public
behavior.

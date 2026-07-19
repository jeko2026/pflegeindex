# Directory Platform — Project Status

## Version

`Architecture Baseline v1.1`

## Current phase

Phase 2 — Platform architecture

## Current iteration

Platform Freeze Audit — Release Candidate preparation

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

Status: **freeze candidate**. The API is stable enough for the PflegeIndex v1.0
Release Candidate, but the freeze becomes final only after the decisions below
are recorded for the RC.

The supported Platform API consists of:

- the `EntryRepository` contract;
- the `ListEntries` use case;
- `ListingCriteria`, `ListingResult` and `EntrySummary` read models;
- `EntryIdentifier`, `EntrySort`, `LocationScope`, `LocationScopeType` and
  `PaginationOptions` domain types.

All other implementation state in DirectoryCore is private. No currently public
class can be made internal without removing a type required by a repository
adapter or a Platform consumer.

Before the final freeze, explicitly decide whether the raw `LocationScope`
constructor remains supported in addition to its typed factories. Its dual use
as listing criteria and entry location metadata should be revisited only in a
future major API version. Dormant convenience operations and enum cases remain
supported for compatibility; they are not a reason to expand the API before
v1.0.

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
| Documentation | Conditional | Platform status is current; release version and changelog still need final RC synchronization. |
| Platform API | Freeze candidate | Public surface is identified; the `LocationScope` constructor decision remains to be recorded. |
| SEO Foundation | Ready | Existing public SEO behavior remains covered and unchanged. |
| DirectoryCore | Ready | Framework-independent API is used by all public PflegeIndex listings. |
| Multi-project support | Proven | A second adapter uses the unchanged Platform API; production project bootstrapping is intentionally out of scope. |

Not yet ready for the Release Candidate:

- final API freeze decision and release-version declaration;
- release notes and changelog synchronization;
- explicit acceptance of the GeoCore reverse dependency and legacy State
  fallback as v1.0 technical debt;
- production deployment, backup and rollback checklist verification.

## Next goal

Finalize the Platform API freeze decision, synchronize release documentation,
and run the Release Candidate verification checklist without expanding
DirectoryCore.

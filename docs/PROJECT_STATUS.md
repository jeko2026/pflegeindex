# Directory Platform — Project Status

## Version

`Architecture Baseline v1.1`

## Current phase

Phase 2 — Platform architecture

## Current iteration

Second Project Adapter — Platform Proof

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

## Tests

97 passed, 2365 assertions.

## Known architecture debt

- GeoCore Eloquent models are not physically separated under `app/Platform`.
- `GeoMunicipality::cities()` creates a reverse dependency on the PflegeIndex `City` model.
- Unmapped legacy cities still require a guarded State scope fallback.
- Region city and type aggregates still use legacy `state_slug` queries.
- Shared SEO and presentation abstractions have not been introduced.

## Next goal

Consolidate repeated PflegeIndex listing composition without moving Laravel concerns into DirectoryCore.

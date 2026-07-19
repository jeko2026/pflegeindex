# PflegeIndex v1.0 — Known Limitations

Status: **Release Candidate (`v1.0.0-rc.1`)**

These are accepted architectural and data constraints of v1.0. They are not a
list of defects and do not change the supported public behavior.

## GeoCore boundary

- GeoCore is implemented with Laravel Eloquent models and is not yet physically
  extracted into the framework-independent Platform namespace.
- `GeoMunicipality::cities()` retains a reverse dependency on the PflegeIndex
  `City` model.

## Legacy geographic data

- Unmapped legacy city records use a guarded State-scope fallback so existing
  Brandenburg listings remain complete.
- Region city and type aggregates still rely on the legacy `state_slug` field.
- `LocationScope` identifiers are interpreted by project adapters and use the
  appropriate existing identifier for each scope type.

## Multi-project support

- FuneralIndex is an architecture proof adapter only. It has no production
  models, persistence, routes, pages or business rules.
- Project selection and production bootstrapping for multiple deployed
  directories are outside the v1.0 scope.

## Presentation

- SEO and presentation abstractions remain project-specific and may be
  generalized only after reuse is proven by another production directory.
- Related-entry and detail-page use cases are not part of the frozen v1.0
  DirectoryCore API.

## Operations

- Production deployment, database backup, rollback and post-deployment checks
  are separate operational steps and are not performed by this documentation
  baseline.

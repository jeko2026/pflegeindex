# PflegeIndex v1.0

Status: **Release Candidate (`v1.0.0-rc.1`)**

This document records the official baseline before final production
preparation. It does not declare the production deployment complete.

## Highlights

- Directory Platform with a framework-independent DirectoryCore.
- Frozen v1.0 Platform API built around contracts, typed value objects, read
  models and the `ListEntries` use case.
- PflegeIndex repository and presentation adapters separated from Platform.
- Multi-project architecture proven by a minimal FuneralIndex adapter without
  changing the Platform API.
- Official Brandenburg GeoCore hierarchy and controlled city mapping.
- Directory, State, District and City facility listings through one
  DirectoryCore flow.
- Public facility detail pages with stable descriptive URLs.
- Search, filters, stable sorting and pagination.
- SEO foundation with canonical metadata, Open Graph, JSON-LD, breadcrumbs,
  robots and XML Sitemap.
- Allowlist architecture tests protecting Platform dependency direction.
- Automated application baseline of 97 tests and 2365 assertions.

## Compatibility

The Release Candidate preserves the existing public URLs, HTML presentation,
SEO metadata and PflegeIndex behavior. No production data migration is part of
this documentation baseline.

## Release status

Architecture, DirectoryCore, testing and release documentation are ready for
the Release Candidate. Production deployment, backup, rollback and
post-deployment verification remain the final preparation stage.

Known constraints accepted for v1.0 are documented in
`KNOWN_LIMITATIONS.md`.

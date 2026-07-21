# Database Deployment Decision

The normal application package never contains a SQLite database. Select and
approve one of the following database procedures separately from the code
package.

## Scenario A — new production installation

Use a separately transferred, reviewed and sanitized SQLite copy that already
contains exactly:

- 257 cities;
- 1,557 facilities;
- 1 GeoCore country;
- 1 GeoCore state;
- 18 GeoCore districts;
- 413 GeoCore municipalities.

Before transfer, verify the schema, row counts, administrator data, protected
contact fields and absence of unintended local/test data. Transfer the database
outside the release directory and outside the public webroot. Record its
SHA-256 checksum separately. The database is data payload, not part of any
application ZIP archive.

## Scenario B — update an existing production database

Do not replace the existing production SQLite file. First place the site in
maintenance mode and create a verified backup outside the public webroot. Run
only the reviewed migrations required by the release.

The GeoCore migrations create schema only; they do not populate GeoCore data or
city relations. GeoCore therefore requires a separate approved data-fix/import
with its own backup, dry-run and verification procedure. Do not run
`php artisan geocore:import-brandenburg` in production: the command deliberately
rejects the production environment.

The normal deployment package must not attempt to infer, copy, replace or
repair the production database automatically.

# PflegeIndex Operations Guide

This guide covers routine operation of the PflegeIndex v1.0 production site.
It does not replace hosting-provider monitoring or the deployment checklist.

## Daily checks

- Confirm `/up` returns HTTP 200 with the exact plain-text body `OK`, then
  confirm the home page returns HTTP 200 over HTTPS. `/up` is a Laravel
  liveness check; it does not query the database or contact external services.
- Open one City and one Facility page and confirm assets load without browser
  console or server errors.
- Check the latest Laravel log entries for new `ERROR`, `CRITICAL` or repeated
  warning messages. Do not publish logs or copy secrets into issue reports.
- Confirm the scheduled SQLite backup completed, is non-empty and is stored
  outside the public web root.
- Check available disk space, especially for the database, logs and backups.
- Confirm the public site does not expose a maintenance or debug page.

## Weekly checks

- Complete a short admin login and logout check.
- Confirm `/sitemap.xml` is valid XML and `/robots.txt` points to its production
  HTTPS URL.
- Check a directory search, pagination link and facility contact link.
- Review failed login patterns and unexpected admin requests in available
  hosting logs.
- Review database and log growth. Confirm Laravel continues to create one
  dated application log per day and removes files older than 30 days.
- Verify that no `.env`, SQLite, backup, log, `public/hot`, development manifest
  or temporary installer is present in the public document root.

## Application logs

Production uses `LOG_CHANNEL=stack`, `LOG_STACK=daily` and
`LOG_DAILY_DAYS=30`. Laravel writes `storage/logs/laravel-YYYY-MM-DD.log` in the
private application directory and Monolog automatically removes application
logs older than 30 days. `storage/logs` must be writable by PHP and must never
be exposed through the public web root. Hosting access/error logs have their
own provider retention policy and are not managed by this Laravel setting.

## Backups

Back up the SQLite database every day and immediately before every migration,
import or production data change. Back up `.env` after an approved configuration
change. Store backups outside the document root and, where possible, on a second
system controlled by the project owner.

Recommended minimum retention:

- 7 daily backups;
- 4 weekly backups;
- 6 monthly backups.

Protect or encrypt backups because the database may contain administrator and
contact-review data. Access must be limited to authorized operators. At least
monthly, restore a backup into a separate non-production directory and verify
that Laravel can read it. A backup that has never been restored is not yet a
verified recovery source.

## Laravel updates

Do not update Laravel directly on production.

1. Review the Laravel release and security notes.
2. Update dependencies in a development branch or isolated staging copy.
3. Review `composer.lock` and application compatibility.
4. Run PHPUnit, Pint and the release verification checklist.
5. Commit the lock file with the tested change.
6. Deploy through `docs/DEPLOYMENT_CHECKLIST.md`.

Major Laravel upgrades require a separate compatibility task and must not be
combined with routine content or data changes.

## Composer package updates

Run these checks in development or CI, not by modifying production directly:

```bash
composer audit --locked --no-dev
composer outdated --direct
```

Update only reviewed packages, keep `composer.lock`, and rerun the complete test
suite. Production installation must use:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
```

Never run an unreviewed `composer update` on the production server.

## Incident response and recovery

1. Preserve the time, symptoms, affected URLs and relevant sanitized logs.
2. If data integrity or error exposure is possible, enable maintenance mode.
3. Confirm whether the fault is code, configuration, database, permissions,
   storage capacity or hosting availability.
4. For a failed deployment, follow the rollback section of
   `docs/DEPLOYMENT_CHECKLIST.md`.
5. For database corruption or unintended mutation, stop writes and restore the
   latest verified backup from before the incident.
6. Rebuild Laravel caches and perform the full release verification checklist.
7. Bring the site online only after public pages and admin authentication pass.
8. Record root cause, recovery actions and preventive follow-up.

If `.env`, `APP_KEY`, administrator credentials or database contents may have
been exposed, treat the incident as a security event and rotate affected
credentials before reopening the site.

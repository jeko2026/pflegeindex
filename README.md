# PflegeIndex

Laravel-Anwendung für das PflegeIndex-Verzeichnis. Der öffentliche Bereich arbeitet ohne Cookies; ausschließlich die geschützte Verwaltung verwendet technisch notwendige Sitzungscookies.

## Lokaler Start

```powershell
php artisan serve
```

Danach ist die Website unter `http://127.0.0.1:8000` erreichbar. Die Verwaltung liegt unter `/admin`.

## Daten neu aufbauen

Die SQLite-Datei wird nicht im Git-Repository gespeichert. Auf einer neuen Installation werden die Tabellen und Verzeichnisdaten so aufgebaut:

```powershell
php artisan migrate --force
php artisan pflegeindex:import ../mvp/data/facilities.json
php artisan pflegeindex:import-suggestions ../mvp/data/enrichment/potsdam-contacts.json
php artisan pflegeindex:create-admin info@pflegeindex.com
```

Manuell geprüfte Kontaktdaten aus einer vorhandenen Datenbank sollten vor einem Umzug gesichert werden. Ein Neuimport überschreibt als gesperrt markierte Kontakte nicht.

## Veröffentlichung

1. Der Webserver muss auf den Ordner `laravel/public` zeigen.
2. `.env.production.example` als Vorlage für die nicht öffentliche `.env` verwenden.
3. Eine neue `APP_KEY` mit `php artisan key:generate` erzeugen.
4. `storage` und `bootstrap/cache` für PHP beschreibbar machen.
5. HTTPS aktivieren und anschließend `php artisan optimize` ausführen.
6. Vor der Freischaltung die vorhandenen Betreiberangaben prüfen und die noch
   offenen Hosting- und Postfachangaben anhand von
   `docs/LEGAL_HOSTING_FACTS_CHECKLIST.md` bestätigen. Unbestätigte Angaben
   dürfen nicht in die Datenschutzerklärung übernommen werden.

Die Admin-Sitzung ist für HTTPS vorbereitet. Der öffentliche Bereich bindet keine externen Skripte, Fonts oder Karten ein; OpenStreetMap wird erst nach einem bewussten Klick geöffnet.

## Production deployment

Production deployments follow the reproducible procedure in
[`docs/DEPLOYMENT_CHECKLIST.md`](docs/DEPLOYMENT_CHECKLIST.md). Before updating
code, place the site in maintenance mode and create a verified copy of the
SQLite database and `.env` outside the public document root.

The standard Git/SSH update uses:

```bash
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

Do not upload the local `.env`, SQLite database, backups, logs, `public/hot` or
development manifests with FileZilla. After deployment, complete
[`docs/RELEASE_VERIFICATION.md`](docs/RELEASE_VERIFICATION.md). Routine checks,
backup retention, dependency updates and recovery are documented in
[`docs/OPERATIONS.md`](docs/OPERATIONS.md).

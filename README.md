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
6. Vor der Freischaltung die echten Betreiberangaben in Impressum und Datenschutz ergänzen.

Die Admin-Sitzung ist für HTTPS vorbereitet. Der öffentliche Bereich bindet keine externen Skripte, Fonts oder Karten ein; OpenStreetMap wird erst nach einem bewussten Klick geöffnet.

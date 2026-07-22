@extends('layouts.app')

@section('title', 'Datenschutz – PflegeIndex')
@section('description', 'Datenschutzerklärung von PflegeIndex.com.')

@push('head')<meta name="robots" content="noindex,nofollow">@endpush

@section('content')
    <section class="page-hero"><div class="container"><p class="breadcrumbs"><a href="{{ route('home') }}">Startseite</a><span>›</span><span>Datenschutz</span></p><h1>Datenschutz</h1><p class="page-hero__lead">Informationen zur Verarbeitung personenbezogener Daten.</p></div></section>
    <div class="container detail-layout legal-content">
        <div class="detail-main">
            <section class="detail-section">
                <h2>1. Verantwortlicher</h2>
                <p><strong>PflegeIndex.com</strong> – Projektbezeichnung<br>Verantwortlicher: Yevhenii V.<br>Herojiv Dnipra Str. 33<br>39800 Horischni Plavni<br>Ukraine<br>E-Mail: <a href="mailto:info@pflegeindex.com">info@pflegeindex.com</a></p>
            </section>

            <section class="detail-section">
                <h2>2. Hosting</h2>
                <p>Diese Website wird bei einem externen Hosting-Dienstleister betrieben. Bei jedem Aufruf werden Anfragen an dessen Webserver übermittelt. Die genaue Anbieterbezeichnung, der Sitz des Anbieters, der Serverstandort und der Status eines Vertrags zur Auftragsverarbeitung sind noch nicht abschließend bestätigt. Diese Angaben müssen vor der Produktionsfreigabe anhand der Vertragsunterlagen ergänzt werden.</p>
            </section>

            <section class="detail-section">
                <h2>3. Server-Logfiles und Anwendungsprotokolle</h2>
                <p>Für die Übertragung einer Webseite verarbeitet der Webserver technisch insbesondere die IP-Adresse, den Zeitpunkt und die angeforderte Adresse. Ob und in welchem Umfang der Hosting-Dienstleister darüber hinaus URL-Parameter, Referrer, Browser- oder Geräteangaben dauerhaft in Zugriffs- oder Fehlerprotokollen speichert, ist noch nicht bestätigt. Auch die Löschfrist dieser Serverprotokolle muss vor der Produktionsfreigabe bestätigt werden.</p>
                <p>Laravel kann bei technischen Fehlern private Anwendungsprotokolle mit Fehlerdetails und betroffenen URLs erzeugen. Diese Protokolle sind auf eine tägliche Rotation mit einer Aufbewahrung von 30 Tagen konfiguriert und dienen ausschließlich Betrieb, Fehleranalyse und Sicherheit.</p>
            </section>

            <section class="detail-section">
                <h2>4. Rechtsgrundlagen</h2>
                <p>Die technische Bereitstellung, Fehleranalyse, Sicherheit und Abwehr missbräuchlicher Zugriffe erfolgen auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO. Unser berechtigtes Interesse liegt im sicheren und zuverlässigen Betrieb des Informationsverzeichnisses. E-Mail-Anfragen werden je nach Inhalt auf Grundlage von Art. 6 Abs. 1 lit. b oder lit. f DSGVO verarbeitet. Gesetzliche Aufbewahrungspflichten beruhen auf Art. 6 Abs. 1 lit. c DSGVO.</p>
            </section>

            <section class="detail-section">
                <h2>5. Suche und Filter</h2>
                <p>Bei der Nutzung von Suche und Filtern werden Suchbegriffe und ausgewählte Filter als URL-Parameter an den Webserver übertragen, um die angeforderten Ergebnisse auszugeben. Abhängig von der noch zu bestätigenden Protokollierung des Hosting-Dienstleisters können solche Parameter Bestandteil von Serverprotokollen sein.</p>
            </section>

            <section class="detail-section">
                <h2>6. Kontaktaufnahme per E-Mail</h2>
                <p>PflegeIndex verwendet kein öffentliches Kontaktformular. Kontakt und Hinweise auf Datenfehler erfolgen über <code>mailto:</code>-Links und werden erst nach einer Aktion der nutzenden Person an das gewählte E-Mail-Programm übergeben. Beim Melden eines Datenfehlers werden der Name der Einrichtung und die Adresse der aktuell geöffneten Seite im Nachrichtentext vorbereitet.</p>
                <p>Bei einer Kontaktaufnahme verarbeiten die beteiligten E-Mail-Anbieter Absenderadresse, Empfängeradresse, Zeitpunkt, technische Metadaten und den Nachrichteninhalt. Anbieter, Serverstandort, Weiterleitungen sowie Löschfristen des Postfachs und seiner Protokolle sind noch nicht bestätigt. Daher kann derzeit keine verbindliche konkrete Speicherdauer für E-Mail-Anfragen genannt werden.</p>
            </section>

            <section class="detail-section">
                <h2>7. Verzeichnis- und Kontaktdaten</h2>
                <p>PflegeIndex.com veröffentlicht Angaben aus öffentlichen Verzeichnissen und öffentlich zugänglichen offiziellen Internetseiten. Die Verarbeitung dient dem berechtigten Interesse an einem auffindbaren Informationsverzeichnis gemäß Art. 6 Abs. 1 lit. f DSGVO. Betroffene können Berichtigung oder Löschung unter <a href="mailto:info@pflegeindex.com">info@pflegeindex.com</a> verlangen.</p>
            </section>

            <section class="detail-section">
                <h2>8. Cookies und Sitzungen</h2>
                <p>Die öffentlichen Verzeichnisseiten starten keine Laravel-Sitzung und setzen nach der geprüften Anwendungskonfiguration keine Cookies. Nur der geschützte Verwaltungsbereich verwendet technisch notwendige Cookies:</p>
                <ul>
                    <li><strong><code>pflegeindex-session</code>:</strong> verschlüsselte Kennung der Administrator-Sitzung; konfigurierte Inaktivitätsdauer 120 Minuten; Secure, HttpOnly und SameSite=Lax.</li>
                    <li><strong><code>XSRF-TOKEN</code>:</strong> Schutz administrativer Formulare vor Cross-Site-Request-Forgery; konfigurierte Dauer 120 Minuten; Secure und SameSite=Lax; für die Formulartechnik durch JavaScript lesbar und deshalb nicht HttpOnly.</li>
                </ul>
                <p>Der Inhalt der zugehörigen Sitzung wird verschlüsselt in der Datenbank gespeichert. Der Sitzungsdatensatz kann daneben Benutzer-ID, IP-Adresse, User-Agent und den Zeitpunkt der letzten Aktivität enthalten. Eine „Angemeldet bleiben“-Funktion wird nicht angeboten. Analyse-, Marketing- oder Third-Party-Cookies werden von der Anwendung nicht eingesetzt. Die Speicherung der notwendigen Administrator-Cookies stützt sich auf § 25 Abs. 2 Nr. 2 TDDDG; die anschließende Verarbeitung erfolgt auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO.</p>
            </section>

            <section class="detail-section">
                <h2>9. Externe Inhalte und Links</h2>
                <p>Die aktiven öffentlichen Seiten laden Stylesheets, JavaScript, Schriftarten und Bilder lokal. Sie binden keine externen Schriftarten, Karten, Videos, Tracking-Pixel, Analyse-Skripte, CDN-Ressourcen oder externen Bilder automatisch ein. Auch der Status-Endpunkt <code>/up</code> liefert ausschließlich lokalen Klartext.</p>
                <p>Links zu Google Maps, offiziellen Quellen und Websites von Einrichtungen stellen erst nach dem Anklicken eine Verbindung zum jeweiligen externen Anbieter her. Ab diesem Zeitpunkt gilt auch die Datenschutzerklärung des externen Anbieters.</p>
            </section>

            <section class="detail-section">
                <h2>10. Speicherdauer</h2>
                <p>Personenbezogene Daten werden nur so lange verarbeitet, wie es für den jeweiligen Zweck oder gesetzliche Pflichten erforderlich ist. Laravel-Anwendungsprotokolle sind auf 30 Tage begrenzt. Administrator-Sitzungen werden nach 120 Minuten Inaktivität ungültig und durch die Sitzungsbereinigung entfernt. Konkrete Fristen des Hosting-Dienstleisters, des E-Mail-Postfachs und der jeweiligen Backups sind noch zu bestätigen und verhindern derzeit eine abschließende Produktionsfreigabe dieser Erklärung.</p>
            </section>

            <section class="detail-section">
                <h2>11. Rechte der betroffenen Personen</h2>
                <p>Betroffene Personen haben nach Maßgabe der gesetzlichen Voraussetzungen das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch. Eine erteilte Einwilligung kann jederzeit mit Wirkung für die Zukunft widerrufen werden. Anfragen können an <a href="mailto:info@pflegeindex.com">info@pflegeindex.com</a> gerichtet werden.</p>
            </section>

            <section class="detail-section">
                <h2>12. Beschwerderecht</h2>
                <p>Betroffene Personen haben das Recht, sich bei einer zuständigen Datenschutzaufsichtsbehörde über die Verarbeitung ihrer personenbezogenen Daten zu beschweren.</p>
            </section>

            <section class="detail-section">
                <h2>13. SSL-/TLS-Verschlüsselung</h2>
                <p>Die Website ist für die verschlüsselte Übertragung über HTTPS vorgesehen. Dadurch werden Inhalte während der Übertragung zwischen Browser und Server gegen ein einfaches Mitlesen geschützt.</p>
            </section>

            <section class="detail-section">
                <h2>14. Änderungen dieser Datenschutzerklärung</h2>
                <p>Diese Datenschutzerklärung wird angepasst, wenn sich die technische Verarbeitung oder die bestätigten Angaben zu Hosting und E-Mail ändern. Stand: 22. Juli 2026.</p>
            </section>
        </div>
    </div>
@endsection

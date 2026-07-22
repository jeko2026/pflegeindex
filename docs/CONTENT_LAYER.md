# PflegeIndex Content Layer

## Ziel

Der Content Layer ergänzt Einrichtungsseiten um neutrale Orientierung, häufige Fragen und sichere interne Verweise. Er hilft Nutzerinnen und Nutzern, vor einer Kontaktaufnahme wichtige organisatorische Punkte zu klären. Die Inhalte enthalten keine medizinische Beratung, keine Bewertung der Einrichtung und keine Aussage zur tatsächlichen Verfügbarkeit einzelner Leistungen oder Plätze.

Die Ergänzung verändert weder URLs noch SEO-Metadaten, strukturierte Daten, DirectoryCore, GeoCore, Trust Layer oder die Datenbank.

## Struktur

Jede Einrichtungsseite enthält nach den individuellen Einrichtungsinformationen:

1. **Was Sie wissen sollten** – vier neutrale Hinweise zu Besichtigung, Leistungsumfang, Verfügbarkeit und Kosten;
2. **Häufige Fragen** – kurze Antworten zu Unterlagen, Besichtigung, Pflegeversicherung und Platzsuche;
3. **Weitere Informationen** – Links auf tatsächlich vorhandene Begriffe im Pflegelexikon;
4. **Fragen?** – ein zurückhaltender Kontaktblock mit ausschließlich vorhandenen Kontaktmöglichkeiten;
5. **Weitere Pflegeeinrichtungen im Ort** – der bestehende, auf drei Einträge begrenzte Block mit Einrichtungen aus derselben Stadt.

## Interne Links

Die gewünschten Themen werden vor der Ausgabe gegen die vorhandene Lexikon-Konfiguration geprüft. Ein Link wird nur erzeugt, wenn der zugehörige Begriff tatsächlich vorhanden ist. Aktuell werden Pflegegrad, Kurzzeitpflege, Ambulante Pflege und Stationäre Pflege verlinkt. Für Pflegeversicherung existiert derzeit keine eigenständige Lexikonseite; deshalb wird dafür kein Link ausgegeben.

Dieses Vorgehen verhindert Platzhalter- und 404-Links. Neu angelegte Lexikonseiten können später durch Ergänzen der bestehenden Konfiguration automatisch freigeschaltet werden.

## Prinzipien

- Aussagen bleiben neutral, allgemein verständlich und frei von medizinischen Empfehlungen.
- Individuelle Leistungen, Kosten, Aufnahmebedingungen und Verfügbarkeit werden nicht behauptet.
- Kontaktaktionen erscheinen nur, wenn die jeweilige Kontaktinformation vorhanden ist.
- Überschriften, Listen, Linkgruppen und ARIA-Beschriftungen bilden eine verständliche Seitenstruktur.
- Die mobile Darstellung verwendet dieselben Inhalte in einer einspaltigen Reihenfolge.
- Es werden ausschließlich bereits geladene Facility-Daten und statische Konfiguration verwendet.
- Der vorhandene Related-Facilities-Block und sein eager loading werden wiederverwendet; es entstehen keine zusätzlichen Abfragen oder N+1-Zugriffe.

## Wiederverwendung in Directory Platform

Der Content Layer bleibt eine projektspezifische Darstellung außerhalb von DirectoryCore. Andere Kataloge können eigene neutrale Hinweise, FAQ-Fragen, Themenlisten und Kontaktformulierungen bereitstellen, ohne die Plattformverträge zu verändern. Dabei sollten Links stets gegen tatsächlich vorhandene Ziele geprüft, katalogspezifische Behauptungen vermieden und vorhandene Ergebnisdaten ohne zusätzliche Abfragen wiederverwendet werden.

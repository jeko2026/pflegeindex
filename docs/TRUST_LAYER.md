# PflegeIndex Trust Layer

## Ziel

Der Trust Layer macht auf Einrichtungsseiten transparent, wie vollständig und technisch plausibel die bei PflegeIndex vorliegenden Informationen sind. Er bewertet ausschließlich die Datenqualität der jeweiligen Karte. Er ist keine Bewertung, Empfehlung oder Qualitätsprüfung der Einrichtung und enthält keine Nutzerbewertungen.

Der Wert wird bei der Seitenausgabe aus bereits geladenen Daten berechnet. Er wird weder in der Datenbank gespeichert noch durch zusätzliche Datenbankabfragen erzeugt.

## Berechnung

| Merkmal | Rohgewicht | Erfüllt, wenn |
| --- | ---: | --- |
| Telefon | 20 | eine plausible Telefonnummer mit mindestens sechs Ziffern vorliegt |
| Website | 15 | eine syntaktisch gültige HTTP- oder HTTPS-Adresse vorliegt |
| E-Mail | 10 | eine syntaktisch gültige E-Mail-Adresse vorliegt |
| Vollständige Adresse | 15 | Adresse, fünfstellige Postleitzahl und Ort vorliegen |
| Beschreibung | 15 | eine redaktionelle oder gespeicherte Beschreibung vorliegt |
| Koordinaten | 10 | gültige Breiten- und Längengrade vorliegen |
| Amtliche Grunddaten | 10 | eine amtliche Quell-ID vorliegt |
| Canonical | 5 | eine gültige Canonical URL sowie City- und Facility-Slug vorliegen |
| Letzte Prüfung | 5 | eine dokumentierte Kontakt- oder Beschreibungsprüfung vorliegt |
| Keine offensichtlichen Fehler | 5 | Pflichtangaben plausibel und vorhandene Kontaktdaten syntaktisch gültig sind |

Die vorgegebenen Rohgewichte ergeben zusammen 110 Punkte. Damit die Oberfläche einen Wert von 0 bis 100 Prozent zeigt, wird die erreichte Rohpunktzahl durch 110 geteilt und kaufmännisch auf eine ganze Prozentzahl gerundet. Die Gewichte selbst bleiben unverändert.

Der Zähler „X von 10 Qualitätsmerkmalen erfüllt“ verwendet dieselben zehn Kriterien. Badges erscheinen nur, wenn der zugehörige Bereich tatsächlich erfüllt ist:

- „Amtliche Daten“ bei vorhandener amtlicher Quell-ID;
- „Kontaktdaten“ bei plausibler Telefonnummer oder gültiger E-Mail-Adresse;
- „Beschreibung“ bei vorhandener Beschreibung;
- „Standort“ bei vollständiger Adresse;
- „Website“ bei gültiger Website-Adresse.

Die aktuelle Facility-Tabelle enthält keine Koordinatenfelder. Deshalb wird dieser Punkt im gegenwärtigen Datenbestand nicht vergeben und es erscheint keine entsprechende Bestätigung. Der Trust Layer liest optionale Koordinaten nur dann aus, wenn sie künftig als bereits geladenes Attribut bereitstehen.

## Darstellung und Barrierefreiheit

Das Panel steht auf Mobilgeräten direkt nach den Schnellkontakt-Aktionen. Auf Desktop bleibt es im kompakten Kopfbereich der Einrichtungsseite. Die ruhige Farbgebung verwendet ausreichende Kontraste. Score und Fortschrittsanzeige besitzen verständliche ARIA-Beschriftungen. Die Erklärung ist als per Tastatur bedienbares `details`-Element umgesetzt und stellt ausdrücklich klar, dass keine Bewertung der Einrichtung erfolgt.

„Stand der Bewertung“ bezeichnet den Zeitpunkt der dynamischen Berechnung. Das Datum behauptet keine fachliche oder redaktionelle Prüfung der Einrichtung. Eine tatsächlich dokumentierte Prüfung wird separat über das Kriterium „Prüfung dokumentiert“ berücksichtigt.

## Verwendung in weiteren Directory-Platform-Katalogen

Die Berechnung ist projektspezifisch und verändert weder DirectoryCore noch GeoCore. Weitere Kataloge können eine eigene, gleichartige Bewertungsregel für ihre Felder und Quellen bereitstellen. Dabei gelten dieselben Produktregeln:

1. nur bereits geladene Daten verwenden;
2. keine Bewertung des gelisteten Anbieters ableiten;
3. Kriterien, Gewichte und Normalisierung dokumentieren;
4. Bestätigungen nur für nachweislich erfüllte Kriterien anzeigen;
5. fehlende Informationen transparent als fehlend behandeln.

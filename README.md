# Workshop-Website (Paper Development Workshop HSG, 3.12.2026)

Landing Page plus Paper-Einreichung mit serverseitigem Deadline-Gate. Sie ist bewusst klein gehalten: statisches Layout, ein schlankes PHP-Backend, SQLite und PHPMailer. Alles läuft auf einem Schweizer Shared Hosting (z. B. Infomaniak). Es gibt keine Cookies für Besucher, kein Tracking, keine CDNs und keine externen Fonts oder Captchas.

**Design:** Die Farben kommen von der Institutsseite [iwi.unisg.ch](https://iwi.unisg.ch/). Gesetzt ist alles mit der Disziplin von anduril.com und palantir.com: eine Schrift, Grösse statt Effekte, Haarlinien statt Karten, Farbe nur als grosse Fläche. Das ist die Vorgabe des Lehrstuhls vom 12.10.2026; den früheren Radar-Look ersetzt sie.

- **Farben:**
  - Weisser Grund, Text `#111111`.
  - HSG-Grün `#00802F` für alle Aktionen, Links und die Frist-Kachel.
  - Dunkelgrün `#0A5E2D` für Faculty, Menü und Footer.
  - Grau `#6F706F` und Hellgrau `#F5F5F5`.
  - Alle Werte stehen oben in `public/assets/site.css`, mit den Kontrastwerten.
- **Schrift:** Source Sans 3 (300/400/600), lokal in `public/assets/fonts/` (SIL OFL).
- **Logo:** Das HSG-Logo liegt in `public/assets/img/hsg-logo.svg`. Die formelle Freigabe durch die HSG-Kommunikation steht noch aus.
- **Vorlagen:**
  - Die Frist-Kachel in `templates/partials/deadline-tile.php` folgt dem Deadline-Gate (offen / noch nicht offen / geschlossen).
  - Solange `submission.deadline_confirmed` in `config/event.php` auf `false` steht, zeigt sie „Provisional“.
- **Ablage:** Der Ordner `design-radar-gruen/` (alter Look) ist nur Vorlage und wird nicht hochgeladen.

> **Stand 13.10.2026:** Datum, Tracks, Faculty, Deadline, Rückmeldung und Panel sind bestätigt; CfP-Text, Abstract-Format, Räume und genaue Agenda sind noch TBA. Siehe [Offene Punkte](#offene-punkte).

## Schnellstart lokal

PHP 8.4 (winget) und Composer liegen unter `%LOCALAPPDATA%\composer-bin\composer.phar`.

```bash
php bin/init-local.php
```

Legt `config/env.php` mit Zufalls-Secret und lokalem Admin-Login an. Benutzername und Passwort stehen oben in dieser Datei.

```bash
php -S localhost:8080 -t public -d upload_max_filesize=12M -d post_max_size=16M -d display_errors=stderr
```

Danach:

- Seite: http://localhost:8080
- Admin: http://localhost:8080/admin/
- Mails landen lokal als Dateien in `storage/mail-outbox/`.

Tests:

```bash
php tests/run.php
```

```bash
php tests/smoke.php
```

`run.php` enthält die Einzeltests (Deadline inkl. Zeitumstellung, Upload-Prüfung, Spam-Schutz, Datenbank). `smoke.php` ist der End-to-End-Test gegen einen echten lokalen Server.

Vorschau für Leute ohne PHP, zum Beispiel für Feedback von Leon:

```bash
php bin/export-preview.php
```

Das Skript erzeugt `export/workshop-vorschau.zip`, etwa 400 KB. Entpacken und `index.html` doppelklicken. Das Formular ist in der Vorschau ausgeschaltet. Nach jeder Änderung einfach neu erzeugen.

**Online-Vorschau:** https://sovereignty-radar.iwi.unisg.ch/pdw/ (GitHub Pages, Branch `gh-pages`; das Grau-Zone-Konto leitet github.io auf diese Domain um)
- Nur statisch, das Formular ist aus. Die Seite liegt auf US-Servern, enthält aber keine Daten, nur Entwurfsinhalte.
- Aktualisieren: Vorschau mit Adresse erzeugen, dann den Inhalt von `export/vorschau/` als neuen Commit auf `gh-pages` pushen.

  ```bash
  php bin/export-preview.php https://sovereignty-radar.iwi.unisg.ch/pdw/
  ```

- Die echte Seite mit Formular kommt auf Schweizer PHP-Hosting, siehe Deployment.

## Aufbau

```
config/event.php     Alle Inhalte, Termine und Regeln an EINER Stelle (kommt ins Git)
config/env.php       Secrets und Server-Einstellungen (nie ins Git, Vorlage: env.example.php)
public/              Web-Root – nur dieser Ordner ist öffentlich
  index.php          Landing Page
  submit.php         Annahme der Einreichung
  admin/             Login, Liste, PDF-Download, ZIP/CSV-Export
  .user.ini          PHP-Upload-Limits für den Server
src/                 Logik (DeadlineGate, PdfUpload, FormGuard, ...)
templates/           Seiten, Formular, Mails
storage/             Uploads, Datenbank, Logs – ausserhalb des Web-Roots
bin/                 init-local, check-env, hash-password, purge
tests/               Einzeltests + End-to-End-Test
```

## Wie die Einreichung funktioniert

> **Seit 14.10.2026 per E-Mail:** Die Extended Abstracts werden nicht hochgeladen, sondern per E-Mail an `submission.email` geschickt (aktuell adrian.bohrer@unisg.ch).
> - `config/event.php` steht dazu auf `submission.method = 'email'`.
> - Die Seite zeigt dann statt des Formulars eine Anleitung: Adresse, Knopf mit vorausgefülltem Betreff, Deadline, Anhang, Inhalt der Mail und Hinweis zur Weitergabe an die Faculty.
> - `submit.php` leitet nur noch auf diese Anleitung weiter und speichert nichts.
> - Die Deadline-Kachel und der Status (offen/geschlossen) funktionieren wie bisher. Die Datenschutzerklärung passt sich an.
> - Der Upload-Weg unten bleibt im Code und lässt sich mit `method = 'form'` wieder einschalten.

- **Deadline:** Der Server prüft sie in `Europe/Zurich`, nicht der Browser.
  - „23:59“ gilt bis 23:59:59.
  - Ab dem 25.10. gilt Winterzeit. Die Seite zeigt deshalb „Zurich time (CET, UTC+01:00)“ an.
  - `grace_minutes` (Standard 5) nimmt verspätete Uploads noch an, angezeigt wird das nicht.
- **Gestaffelter Go-live:**
  - `draft: true` zeigt ein Banner und hält Suchmaschinen fern.
  - Ist `draft: false`, nimmt die Seite erst Einreichungen an, wenn `privacy.approved: true` gesetzt ist. Die Info-Seite kann also live gehen, bevor die Datenschutzstelle die Texte freigegeben hat.
- **Neue Version:** Wer mit derselben E-Mail-Adresse noch einmal einreicht, erzeugt eine neue Version. Es zählt die letzte.
  - Nur ein exakt gleicher Reload (gleiche Datei, gleiche Angaben, innert 10 Minuten) erzeugt keine neue Version.
  - Jede Korrektur wird gespeichert, auch wenn die Datei gleich bleibt.
  - Kommt eine neue Version unter anderem Namen als Version 1, markiert die Admin-Liste sie mit „name differs from v1“.
- **Bestätigung:** Die Seite zeigt die Einreichungs-ID an, und es geht eine Mail an die einreichende Person. Die Orga bekommt eine kurze Notiz ohne Paper. Links aus Freitext werden in Mails entfernt, weil die Adresse nicht verifiziert ist.
- **Spam-Schutz** ohne Drittanbieter:
  - ein unsichtbares Feld
  - ein signierter Zeitstempel: zu schnell oder zu alt wird abgelehnt
  - höchstens 20 gespeicherte Einreichungen pro Stunde und Netzwerk (IPv6 pro /64)
  - eine Obergrenze von 400 Uploads insgesamt und ein Stopp bei fast vollem Speicher

  IP-Adressen werden nur gehasht gespeichert und nach 24 Stunden gelöscht.

## Sicherheit (Kurzfassung)

- **Uploads:**
  - Nur echte PDFs: Die Datei muss mit `%PDF-` beginnen und als PDF erkannt werden, höchstens 10 MB.
  - Sie werden unter Zufallsnamen in `storage/` abgelegt, ausserhalb des Web-Roots.
  - Abrufbar sind sie nur für eingeloggte Admins, und zwar als Download, nie im Browser angezeigt.
- **Admin:**
  - Session-Login mit bcrypt-Hashes, CSRF-Token, Begrenzung der Login-Versuche und Ablauf nach 2 Stunden Inaktivität.
  - Ein neuer Passwort-Hash in `env.php` beendet alle laufenden Sessions dieses Kontos.
  - Admin-Seiten werden nicht zwischengespeichert. Exporte räumen ihre temporären ZIPs auch bei abgebrochenem Download weg.
- **Geprüft:** Am 9.10. gab es eine unabhängige Sicherheitsprüfung mit vier Schwerpunkten und Gegenprüfung. Ergebnis: 22 Funde, keiner kritisch oder hoch. Die zwei mittleren (Korrekturen gingen verloren, Datenbank-Sperre unter Last) sind behoben, ebenso die meisten niedrigen.
- **Allgemein:**
  - Strikte Content-Security-Policy, keine fremden Domains.
  - Alle Ausgaben werden escaped, Datenbankabfragen sind parametrisiert.
  - Der CSV-Export ist gegen Excel-Formeln abgesichert.
- **Arbeitsregel:** Keine echten Einreichungen oder Teilnehmerdaten in Claude oder andere KI-Dienste geben. Zum Testen nur erfundene Daten verwenden.

## Deployment (Infomaniak Web Hosting)

1. Das Konto gehört der HSG bzw. dem Lehrstuhl, der Entwickler bekommt nur Admin-Zugang. Domain bestellen, PHP 8.4 einstellen.
2. **Den Document Root der Site auf `public/` setzen.** Falls das nicht geht, schützt die `.htaccess` im Hauptordner alle anderen Ordner.
3. Dateien per SFTP/SSH hochladen. `config/env.php`, `storage/*` und `vendor/` bleiben aussen vor. Auf dem Server dann:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

4. Auf dem Server `config/env.php` aus `env.example.php` anlegen:
   - `base_url` mit https
   - `debug => false`
   - ein neues `app_secret`
   - Admin-Hashes über `php bin/hash-password.php`
   - SMTP-Zugang der Event-Mailadresse (Port 587)
5. Im Manager „HTTPS erzwingen“ aktivieren. Für die Domain SPF, DKIM und DMARC einrichten. **Absender nie @unisg.ch**, weil unisg.ch Mails von fremden Servern in Quarantäne schiebt.
6. Auf dem Server prüfen, bis keine FAIL-Zeilen mehr kommen:

   ```bash
   php bin/check-env.php
   ```

7. Eine Test-Einreichung schicken und die Bestätigungsmail an eine unisg.ch-Adresse, Gmail und Outlook prüfen (auch den Spam-Ordner).
   - Im Admin-Bereich unten steht die Adresse, die der Server von dir sieht. Sie muss deine echte öffentliche IP sein. Ist sie stattdessen eine interne Proxy-Adresse, teilen sich alle Besucher dasselbe Limit.
   - Den Ordner `design-radar-gruen/` nicht hochladen.
8. Die Infomaniak-Gratisphase endet nach 30 Tagen, also vor dem 11.11. auf ein Bezahlabo umstellen.

## Nach dem Event

**Bei Einreichung per E-Mail (aktuell):** `purge.php` erreicht die Mails nicht. Von Hand löschen:
- alle Einreichungs-Mails und Anhänge im Postfach (auch „Gesendet“ und „Gelöschte Elemente“);
- die an die Faculty weitergeleiteten Kopien: die Faculty bitten, sie zu löschen;
- lokale Downloads.

Zuerst ohne Löschen schauen, was betroffen wäre:

```bash
php bin/purge.php
```

Dann alle Einreichungen, PDFs, Mails, Sessions und Logs löschen:

```bash
php bin/purge.php --confirm
```

Den Termin dafür legt `privacy.delete_after` fest. Danach die Seite abschalten oder auf eine reine Info-Seite umstellen. Nicht vergessen: Die Backups des Hosters laufen nach 7 Tagen aus, und heruntergeladene ZIPs auf den Rechnern der Organisatoren müssen separat gelöscht werden.

## Offene Punkte

Alles hier gehört in `config/event.php` bzw. `config/env.php`. Mit `php bin/check-env.php` siehst du, welche Werte noch TBA sind.

**Entscheidungen (Leon/Marco)**

- [ ] Was heisst „digital souverän“ konkret? Nur Schweiz, EU okay, oder muss es HSG-Infrastruktur sein?
- [ ] Domain, und wem gehören Domain und Hosting (HSG/Lehrstuhl)? Wer zahlt (Kostenstelle)?
- [ ] Rolle des Entwicklers: HSG-intern oder extern? Davon hängt ab, ob ein ADV nötig ist.

**Design (Leon/Adrian)**

- [x] Farben nach iwi.unisg.ch, Look an Anduril/Palantir angelehnt (Vorgabe Lehrstuhl, 12.10.2026). Die verbindliche Freigabe für Corporate Design und Logo durch die HSG-Kommunikation steht noch aus.
- [x] Hero: weiss mit grüner Frist-Kachel statt Bild (12.10.2026)

**Inhalte**

- [x] Format und Datum: eintägiger Paper Development Workshop am Do 3.12.2026 (Feedback 13.10.2026)
- [x] Zwei Tracks: Track 1 „Data Platforms and Ecosystems“ (Leimeister, Hein; ersetzt „Data & Digital Sovereignty“, 14.10.), Track 2 „Data Governance“ (Legner, Aaltonen)
- [x] Keine Keynote und keine 1:1-Coachings; Panel (45 Min.) mit allen vier Faculty, moderiert von Andreas Hein; dritter Paper-Block am Nachmittag (14.10.)
- [ ] Endgültiger Titel (aktuell „Paper Development Workshop on Data Studies“), Uhrzeiten, Raum (Raumantrag beim Events Office), hybrid ja oder nein, Kosten
- [ ] Call-for-Papers-Text mit dem Themenfeld pro Track, Faculty mit Bios und Fotos (nur mit Freigabe), Anreise
- [ ] Programm: Es ist wie beim CHAIS PDW Bern aufgebaut (Vorlage in `material/`, nicht hochladen). Die genaue Agenda folgt; offen sind:
  - Räume der zwei Tracks
  - Aufteilung der 30 Minuten pro Paper in Präsentation und Feedback
  - Ort des Mittagessens
  - Slots in `config/event.php` an das finale Programm anpassen
  - Nach der Auswahl Namen und Titel in `config/event.php` eintragen, nur mit Einwilligung ins öffentliche Programm (in der Einreichungs-Mail erfragt; im Formular-Modus per Häkchen)

**Einreichung**

- [x] Art: Extended Abstract (Feedback 13.10.2026)
- [x] Struktur des Extended Abstract: Emerald-Vorlage wie beim WISIC (Leon, 14.10.)
- [x] Länge des Extended Abstract: max. 2 Seiten (Leon, 14.10.)
- [ ] Pflichtfelder, anonymisiert ja oder nein
- [x] Deadline Do 5.11.2026 (Uhrzeit 23:59 Zürich als Annahme), Zu- und Absagen am 12.11.2026
- [ ] Uhrzeit der Deadline bestätigen, Kulanz ja oder nein
- [ ] Anmeldung nur zum Zuhören (noch nicht gebaut)?

**Personen und Mail**

- [x] Einreichung per E-Mail an adrian.bohrer@unisg.ch (persönliche Adresse, Entscheid 14.10.2026)
- [ ] Funktionsmailbox für Kontakt (`contact.email` ist noch ein Platzhalter), Vertretung
- [ ] Empfänger der Orga-Benachrichtigungen, Admin-Accounts
- [ ] Wie gehen die Paper an die Faculty? Bei E-Mail-Einreichung per Weiterleitung oder als Sammel-ZIP von Hand.

**Recht**

- [ ] Datenschutzerklärung und Impressum nach HSG-Event-Vorlage, Freigabe durch datenschutz@unisg.ch, danach `privacy.approved = true`
- [ ] Löschfrist (`privacy.delete_after`), Logo- und CI-Freigabe durch die Kommunikation (Farben in `public/assets/site.css`, Logo in `public/assets/img/hsg-logo.svg`)

# Workshop-Website (Doktorandenseminar HSG, 13.11.2026)

Landing Page plus Paper-Einreichung mit serverseitigem Deadline-Gate. Sie ist bewusst klein gehalten: statisches Layout, ein schlankes PHP-Backend, SQLite und PHPMailer. Alles läuft auf einem Schweizer Shared Hosting (z. B. Infomaniak). Es gibt keine Cookies für Besucher, kein Tracking, keine CDNs und keine externen Fonts oder Captchas.

**Design:** Look des Sovereignty Radar. Die Übergabe dazu liegt lokal in `design-radar-gruen/HANDOVER.md` und ist nicht im Repo. Akzentfarbe ist **HSG-Grün `#00802F`**, wie auf unisg.ch, statt des Vorschlags `#25D07A` aus der Übergabe.

- Weil das HSG-Grün dunkler ist, steht auf den grünen Knöpfen weisse Schrift.
- Links und kleine Labels nutzen eine helle Abstufung im HSG-Farbton (`#69D490`).
- Das dunkle HSG-Grün `#0A5E2D` steht als `--rg-accent-dark` bereit.
- Alle Farbwerte stehen oben in `public/assets/radar-gruen.css`.

- Die Bausteine stecken in `public/assets/radar-gruen.css` (Präfix `rg-`), die Schriften liegen lokal in `public/assets/fonts/` (SIL OFL).
- Projektspezifische Ergänzungen stehen in `public/assets/style.css` (Präfix `ws-`).
- Das Radar-Signet ist bewusst nicht übernommen.
- Der Ordner `design-radar-gruen/` ist nur Vorlage und wird nicht hochgeladen.

> **Stand:** lokales Gerüst. Alle Inhalte sind Platzhalter (**TBA**) und kommen von Leon/HSG. Siehe [Offene Punkte](#offene-punkte).

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

**Online-Vorschau:** https://grau-zone.github.io/pdw/ (GitHub Pages, Branch `gh-pages`)
- Nur statisch, das Formular ist aus. Die Seite liegt auf US-Servern, enthält aber keine Daten, nur Entwurfsinhalte.
- Aktualisieren: Vorschau mit Adresse erzeugen, dann den Inhalt von `export/vorschau/` als neuen Commit auf `gh-pages` pushen.

  ```bash
  php bin/export-preview.php https://grau-zone.github.io/pdw/
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

- [x] Grünton: HSG-Grün `#00802F`. Die verbindliche Freigabe für Corporate Design und Logo durch die HSG-Kommunikation steht noch aus.
- [ ] Eigenes Zeichen statt grünem Punkt? Das Radar-Signet bewusst nicht übernommen
- [ ] Hero-Visual (Bild oder Grafik)? Das Radar-Original hat eine animierte Grafik rechts im Hero

**Inhalte**

- [ ] Titel und Format (PDW oder Doktorandenseminar, Thema „data studies“?), finales Datum, Uhrzeit, Raum (Raumantrag beim Events Office), hybrid ja oder nein, Kosten
- [ ] Call-for-Papers-Text, Faculty mit Bios und Fotos (nur mit Freigabe), Anreise
- [ ] Programm: Es ist wie beim CHAIS PDW Bern aufgebaut (Vorlage in `material/`, nicht hochladen). Offen sind:
  - Räume und Leitung der zwei Tracks
  - Aufteilung der 30 Minuten pro Paper in Präsentation und Feedback
  - Keynote: Person und Thema
  - Ort des Mittagessens
  - Anzahl der Slots: aktuell 10 (3 + 2 pro Track), die Zahl begrenzt die angenommenen Paper
  - Nach der Auswahl Namen und Titel in `config/event.php` eintragen, nur mit Einwilligung ins öffentliche Programm (Häkchen beim Einreichen)

**Einreichung**

- [ ] Art (Full Paper oder Extended Abstract), Seitenlimit, Pflichtfelder
- [ ] Deadline mit Uhrzeit, Kulanz ja oder nein, Datum der Zu- und Absagen
- [ ] Anmeldung nur zum Zuhören (noch nicht gebaut)?

**Personen und Mail**

- [ ] Funktionsmailbox für Kontakt und Reply-To (keine persönliche Adresse), Vertretung mit Admin-Zugang
- [ ] Empfänger der Orga-Benachrichtigungen, Admin-Accounts
- [ ] Wie gehen die Paper an die Faculty (ZIP aus dem Admin-Bereich)?

**Recht**

- [ ] Datenschutzerklärung und Impressum nach HSG-Event-Vorlage, Freigabe durch datenschutz@unisg.ch, danach `privacy.approved = true`
- [ ] Löschfrist (`privacy.delete_after`), Logo- und CI-Freigabe durch die Kommunikation (Farben in `public/assets/style.css`)

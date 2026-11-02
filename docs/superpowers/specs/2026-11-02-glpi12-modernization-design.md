# simcard 2.0.0 – Modernisierung für GLPI 11 + 12

Datum: 2026-11-02 · Status: Entwurf zur Freigabe

## 1. Ziel und Rahmen

Das Plugin wurde aus GLPI 9.x-Code nach GLPI 10/11 portiert, folgt aber strukturell noch dem 9.x-Stil
(`inc/`-Autoloading, echo-HTML-Formulare, Roh-SQL). Ziel ist eine neue Major-Version **2.0.0**, die

- auf **GLPI 11.0.x und 12.0.x** fehlerfrei läuft (`requirements`: min `11.0.0`, max `12.0.99`),
- den aktuellen GLPI-Plugin-Standard umsetzt (PSR-4 `src/`, Namespace `GlpiPlugin\Simcard`, Twig, Migration-API),
- bekannte Fehler und Sicherheitsprobleme behebt.

**Entscheidungen (mit Felix abgestimmt):**

| Thema | Entscheidung |
|---|---|
| Versionsbereich | GLPI 11 + 12. GLPI 10 bleibt auf der Linie 1.3.x (nur Wartung). |
| Ansatz | Vollmodernisierung (Namespace, Twig, Migration-API). |
| PIN/PUK | Verschlüsselt mit `GLPIKey` speichern, Bestand migrieren. |
| Alte Logs mit PIN/PUK im Klartext | Bleiben unangetastet. |
| Testumgebung | Docker-Setup (GLPI 11 + 12, MariaDB) im Repo unter `tools/`. |

**PHP:** ≥ 8.2 (Untergrenze von GLPI 11). GLPI 12 verlangt selbst ≥ 8.3. Code muss unter 8.2 und 8.3+ laufen.

## 2. Bekannte Probleme, die 2.0.0 beheben muss

| # | Problem | Ursache | Behebung |
|---|---|---|---|
| P1 | `Invalid relations declared between "glpi_plugin_simcard_simcardtypes" and "glpi_plugin_simcard_simcards" … not a foreign key field of "PluginSimcardSimcardtype"` (u. a. beim Inventar-Import) | `DbUtils::getItemTypeForTable()` leitet `PluginSimcardSimcardtype` ab; `fixItemtypeCase()` kann bei Legacy-Dateien `inc/simcardtype.class.php` die Binnen-Großschreibung nicht rekonstruieren, solange die Klasse noch nicht geladen ist. Der anschließende Vergleich mit dem (inzwischen geladenen) `PluginSimcardSimcardType` per `!==` schlägt fehl. Betrifft SimcardType, SimcardSize, SimcardVoltage, PhoneOperator. | PSR-4-Klassen in `src/` mit passender Datei-Schreibweise; `fixItemtypeCase()` löst sie korrekt auf. |
| P2 | Massenaktion „Entität übertragen“ fatal unter GLPI 12 | `transfer()` der Dropdowns nutzt `Toolbox::addslashes_deep()` (in 12 entfernt) und Roh-SQL | `transfer()` über `getFromDB()` + `import()` neu schreiben. |
| P3 | `front/simcard_item.form.php` fatal unter GLPI 12 bei unbekannter Aktion | `Html::displayErrorAndDie()` in 12 entfernt | `BadRequestHttpException` werfen. |
| P4 | XSS / kaputte Werte mit Apostroph im Formular | Feldwerte ungeescaped in echo-HTML | Twig-Formular mit GLPI-Field-Makros (auto-escaped). |
| P5 | Fehlende Rechteprüfung in `front/simcard.form.php` beim Anzeigen | `PluginSimcardSimcard::canView();` ohne Auswertung | Standard-Controller-Muster mit `check()` / `checkRight()`. |
| P6 | PIN/PUK im Klartext in DB und in der Historie | Kein Schutz sensibler Felder | Verschlüsselung + `blacklist_on_history`. |
| P7 | Jedes Upgrade führt `ALTER TABLE … ENGINE=InnoDB` auf allen Tabellen aus | Altlast | Entfällt; versionierte Migrationsschritte. |
| P8 | Signatur-Inkompatibilitäten mit GLPI 12 | Alle `CommonGLPI`/`CommonDBTM`-Methoden haben in 12 native Typen; `countElementsInTable()`/`getAllDataFromTable()` geänderte Signaturen | Overrides mit kompatiblen Typen deklarieren; Aufrufe anpassen. |
| P9 | Tote/kaputte Hooks | `headings*` (seit 0.85 tot), `migratetypes` mit falschem Klassennamen `PluginSimcardsSimcard`, `csrf_compliant` (in 12 entfernt) | Entfernen. |

## 3. Struktur

```
simcard/
├── setup.php               Konstanten, plugin_init_simcard(), plugin_version_simcard()
├── hook.php                install/uninstall, DB-Relations, Dropdowns, Such-Optionen, Purge, ProfileRight-Sync, Datainjection
├── composer.json           PSR-4 "GlpiPlugin\\Simcard\\": "src/", php >=8.2
├── src/
│   ├── Simcard.php             CommonDBTM – das Asset
│   ├── Simcard_Item.php        CommonDBRelation – Verknüpfung zu Computer/Peripheral/Phone/Printer/NetworkEquipment
│   ├── SimcardSize.php         CommonDropdown
│   ├── SimcardVoltage.php      CommonDropdown
│   ├── SimcardType.php         CommonDropdown
│   ├── PhoneOperator.php       CommonDropdown
│   ├── Profile.php             Rechte-Tab am Profil
│   ├── SimcardInjection.php    Datainjection-Integration
│   └── Install.php             install(), upgrade(), uninstall() inkl. versionierter Schritte
├── templates/
│   ├── simcard.html.twig           Hauptformular (extends generic_show_form.html.twig)
│   └── simcard_item.html.twig      Verknüpfungs-Tab (beide Richtungen)
├── front/
│   ├── simcard.php                 Suchliste
│   ├── simcard.form.php            CRUD
│   └── simcard_item.form.php       Verknüpfen / Trennen
├── locales/                       unverändert (Domain `simcard`), neue Strings in .pot/.po nachziehen, .mo neu kompilieren
└── tools/docker/                  docker-compose für GLPI 11 + 12 (nur Entwicklung, nicht im Release-Archiv)
```

Der Ordner `inc/` entfällt vollständig. Tabellennamen bleiben unverändert, da
`GlpiPlugin\Simcard\SimcardType` → `glpi_plugin_simcard_simcardtypes` usw.

**Klassen-Mapping (alt → neu):**

| Alt | Neu |
|---|---|
| `PluginSimcardSimcard` | `GlpiPlugin\Simcard\Simcard` |
| `PluginSimcardSimcard_Item` | `GlpiPlugin\Simcard\Simcard_Item` |
| `PluginSimcardSimcardSize` | `GlpiPlugin\Simcard\SimcardSize` |
| `PluginSimcardSimcardVoltage` | `GlpiPlugin\Simcard\SimcardVoltage` |
| `PluginSimcardSimcardType` | `GlpiPlugin\Simcard\SimcardType` |
| `PluginSimcardPhoneOperator` | `GlpiPlugin\Simcard\PhoneOperator` |
| `PluginSimcardProfile` | `GlpiPlugin\Simcard\Profile` |
| `PluginSimcardSimcardInjection` | `GlpiPlugin\Simcard\SimcardInjection` |

Für jede Zeile registriert `setup.php` ein `class_alias(neu, alt)`, damit Fremdcode (andere Plugins,
eigene Skripte) weiterläuft. Die Aliase werden erst *nach* der itemtype-Migration relevant und gelten als deprecated.

## 4. Funktionsumfang (bleibt erhalten)

- Asset-Felder wie bisher: Name, Status, Standort, Typ, Größe, Spannung, Provider, Benutzer/Gruppe, Techniker/Tech-Gruppe,
  Inventarnummer, Rufnummer, IMSI (`serial`), PIN 1/2, PUK 1/2, Kommentar, „Ticket-zuordenbar“ (`is_helpdesk_visible`).
- Vorlagen (`is_template`/`template_name`), inkl. Menü-Links „Hinzufügen“/„Vorlagen“.
- Tabs: Verknüpfte Elemente, Netzwerkports, Dokumente, Infocom, Verträge, Tickets/Probleme (wenn `is_helpdesk_visible`), Notizen, Historie, Ereignisse.
- `Plugin::registerClass` mit `assignable_types`, `document_types`, `contract_types`, `ticket_types`, `helpdesk_visible_types`,
  `infocom_types`, `unicity_types`, `reservation_types`, `location_types`; dazu `state_types` und `globalsearch_types`.
  Jeder Schlüssel wird gegen GLPI 11 und 12 geprüft; ein nicht mehr existierender Schlüssel wird weggelassen statt eine Warning auszulösen.
- Recht `simcard:simcard` mit Zusatzbit `SIMCARD_ASSOCIATE_TICKET = 128` und Sync nach `helpdesk_item_type` (Hook auf `ProfileRight` add/update).
- Such-Optionen ab ID 10126 auf den verknüpfbaren Typen (Name, Rufnummer, IMSI).
- `item_purge`-Hook auf den verknüpfbaren Typen entfernt Verknüpfungen.
- Massenaktion „Entität übertragen“ (inkl. Dropdown-Transfer).
- Integrationen: Datainjection, Order (`$ORDER_TYPES`), Uninstall (`$UNINSTALL_TYPES`) – jeweils nur, wenn das Plugin aktiv ist.
- `Simcard_Item::registerItemtype()` zum Erweitern der verknüpfbaren Typen zur Laufzeit.

**Entfällt:** Such-Optionen 413–416 (PIN/PUK) – auf Chiffretext nicht sinnvoll; `check_prerequisites` (GLPI prüft über `requirements`);
`global $LANG`; `unglobalize`-Zweig.

## 5. Datenmodell und Migration

### 5.1 Neuinstallation (`Install::install()`)

- Tabellen über `Migration`-API (`$migration->addField`, `addKey`, bzw. `Migration::createTable` falls in 11+12 verfügbar – sonst
  `CREATE TABLE` per `$DB->doQuery()` mit `DBConnection::getDefault*`). Spalten wie heute; PIN/PUK-Spalten `varchar(255)`
  ohne Index.
- Dropdown-Defaults wie heute (Größen, Spannungen, Typen).
- Display-Preferences für `GlpiPlugin\Simcard\Simcard` (1, 412, 80, 411, 410).
- Profil des installierenden Benutzers erhält Vollzugriff inkl. Bit 128, READNOTE, UPDATENOTE.

### 5.2 Upgrade (`Install::upgrade()`)

Die zuvor installierte Version wird vor dem Upgrade aus `glpi_plugins.version` gelesen (`$from`). Schritte:

1. **itemtype-Umbenennung** (immer, idempotent): für jede Zeile des Klassen-Mappings
   `$migration->renameItemtype($alt, $neu, false)`. Aktualisiert alle `itemtype`-Spalten der DB (Tickets, Logs, Dokumente,
   Infocoms, Display-Preferences, SavedSearches, Reservierungen, Datainjection-Modelle, …). Zusätzlich wird `helpdesk_item_type`
   (JSON) in `glpi_profiles` per Code umgeschrieben, da `renameItemtype` JSON-Felder nicht abdeckt.
   *Im Plan zu verifizieren:* Verhalten von `renameItemtype` in 11 und 12 bei identischem Tabellennamen und welche Spalten es abdeckt
   (z. B. `itemtype_1`/`itemtype_2`, `itemtype_link`, Datainjection-Tabellen).
2. **PIN/PUK verschlüsseln** (nur wenn `version_compare($from, '2.0.0', '<')`): jede nicht-leere `pin`, `pin2`, `puk`, `puk2`
   mit `(new GLPIKey())->encrypt()` neu schreiben (direkt per `$DB->update`, ohne History). Indizes `pin`, `pin2`, `puk`, `puk2` droppen.
   Display-Preferences mit IDs 413–416 entfernen.
3. Altlasten entfernen, falls vorhanden: `glpi_plugin_simcard_configs`, `glpi_plugin_simcard_profiles`.
4. Kein `ALTER … ENGINE` mehr.

Alle Schritte laufen in einer `Migration` und werden mit `executeMigration()` abgeschlossen. Ausgaben nur über die in 11+12
vorhandene Migration-API (keine `display*`-Methoden).

### 5.3 Laufzeit

- `Simcard::prepareInputForAdd/Update`: PIN/PUK-Felder verschlüsseln; leeres Feld im Update-Formular = Wert unverändert
  lassen, explizites Leeren über Checkbox „PIN/PUK löschen“ (wie bei Passwortfeldern im Core).
- Anzeige: Passwortfeld mit Anzeigen-Button, entschlüsselt nur für Benutzer mit UPDATE-Recht; ohne UPDATE-Recht wird der Abschnitt nicht gerendert.
- `Simcard::getFieldsToBlacklistOnHistory()` (bzw. `$blacklist_on_history`) enthält `pin`, `pin2`, `puk`, `puk2`.
- Bestehende Log-Einträge bleiben unverändert (Entscheidung).

### 5.4 Deinstallation (`Install::uninstall()`)

Tabellen droppen; `DisplayPreference`, `SavedSearch`, `DropdownTranslation`, `Item_Ticket`, `Item_Problem`, `Document_Item`,
`Infocom`, `Contract_Item`, `Log`, `Notepad`, `Reservation(Item)`, `NetworkPort` für alle Plugin-itemtypes per `deleteByCriteria`;
`ProfileRight` `simcard:simcard` entfernen; `helpdesk_item_type` in Profilen bereinigen; Datainjection-Modelle wie heute.

## 6. Oberfläche

- **Hauptformular** `templates/simcard.html.twig` extends `generic_show_form.html.twig`, Felder über `components/form/fields_macros.html.twig`
  (`textField`, `dropdownField`, `yesNoField`, `textareaField`, `passwordField`). Vorlagen-Modus, Header, Buttons, Entity-Auswahl kommen von GLPI.
  Hinweis „Verknüpftes Element“ beim Anlegen aus einem Asset heraus (`_itemtype`/`_items_id`) bleibt erhalten.
- **Verknüpfungs-Tab** `templates/simcard_item.html.twig`: Formular zum Hinzufügen (auf der SIM-Karte: Itemtype + Item; auf dem Asset:
  SIM-Karte, bereits verknüpfte ausgeschlossen) und Liste über `components/datatable.html.twig` mit Massenaktion „Trennen“.
  Spalten: Typ, Entität, Name (Link), Seriennummer, Inventarnummer.
- **Profil-Tab**: `Profile::displayRightsChoiceMatrix()` wie bisher; Legende nur wenn in 11+12 vorhanden.
- **Icon**: `ti ti-device-sim`.

## 7. Controller und Hooks

- Kein `include('../../../inc/includes.php')` (deprecated in 12, entfernt in 13). Front-Skripte laufen über GLPIs Legacy-Router.
- `front/simcard.php`: `Session::checkRight(Simcard::$rightname, READ)`; `Html::header(...)`, `Search::show(Simcard::class)`, `Html::footer()`.
- `front/simcard.form.php`: Standardmuster (`add`/`delete`/`restore`/`purge`/`update` mit `check()`; sonst `Simcard::displayFullPageForItem()`
  falls in 11+12 vorhanden, sonst `Html::header` + `display()`). Event-Logs wie bisher.
- `front/simcard_item.form.php`: `add` / Massenlöschen mit `check()`; sonst `BadRequestHttpException`.
- `setup.php`: `PLUGIN_SIMCARD_VERSION = '2.0.0'`, `PLUGIN_SIMCARD_MIN_GLPI = '11.0.0'`, `PLUGIN_SIMCARD_MAX_GLPI = '12.0.99'`;
  `plugin_init_simcard(): void`; `plugin_version_simcard(): array` mit `requirements` (glpi min/max, php min 8.2);
  `plugin_simcard_check_config(bool $verbose = false): bool` bleibt (GLPI ruft sie bei Install/Aktivierung auf) und gibt `true` zurück.
  Kein `csrf_compliant`, keine `headings*`, kein `migratetypes`, kein `minGlpiVersion`.
- Alle Overrides von `CommonGLPI`/`CommonDBTM`-Methoden mit Signaturen, die gegen GLPI 11 *und* 12 kompatibel sind
  (im Plan je Methode gegen beide Versionen abgleichen).
- Datenbankzugriff ausschließlich über `$DB->request([...])`/`$DB->update/insert/delete`; kein String-SQL außer DDL.

## 8. Test und Abnahme

Kein PHPUnit (bräuchte vollständige GLPI-Testinfrastruktur). Stattdessen:

1. `php -l` auf allen PHP-Dateien unter PHP 8.2 und 8.3 (im Docker-Container).
2. **Docker-Setup** `tools/docker/docker-compose.yml`: MariaDB + GLPI 11.0.x + GLPI 12.0.x (offizielle `glpi/glpi`-Images, Tags im Plan festlegen),
   Plugin per Bind-Mount nach `plugins/simcard`. README-Abschnitt zum Starten. `tools/` wird nicht ins Release-Archiv gepackt.
3. **Manuelle Abnahme-Checkliste** (je GLPI 11 und 12, per Browser-Pane), `php-errors.log` und `glpi.log` danach ohne Plugin-Warnings:
   - Neuinstallation + Aktivierung; Menü unter *Assets* sichtbar.
   - Upgrade einer 1.3.1-Installation mit Testdaten (SIM-Karte mit Ticket, Dokument, Infocom, Verknüpfung, Display-Prefs, PIN/PUK):
     Daten vorhanden, itemtypes umbenannt, PIN/PUK verschlüsselt und im Formular korrekt entschlüsselt, Logs unverändert.
   - Anlegen, Bearbeiten (inkl. Wert mit Apostroph und `<script>`), Löschen, Wiederherstellen, Endgültig löschen.
   - Anlegen über Vorlage.
   - Verknüpfen/Trennen aus beiden Richtungen; Purge eines Computers entfernt Verknüpfung.
   - Massenaktion „Entität übertragen“.
   - Suche auf Computer mit Optionen 10126–10128.
   - Ticket mit SIM-Karte als Element; Recht 128 an/aus wirkt auf `helpdesk_item_type`.
   - Benutzer ohne UPDATE-Recht sieht keine PIN/PUK.
   - Kein Auftreten der P1-Warning nach Aufruf eines Dropdowns/Löschens eines Assets mit Relationsprüfung.
   - Deinstallation hinterlässt keine Plugin-Tabellen/-Rechte.

## 9. Nicht im Umfang

- GLPI 10-Unterstützung in 2.x.
- Neue Funktionen (z. B. weitere verknüpfbare Typen, API-Erweiterungen).
- Bereinigung alter Log-Einträge mit PIN/PUK im Klartext.
- Automatisierte Test-Suite / CI.

## 10. Offene Punkte für den Plan (zu verifizieren, keine Designfragen)

- Exakte Signaturen der überschriebenen Methoden in GLPI 11 vs. 12.
- Verfügbarkeit von `Migration::createTable`, `CommonDBTM::displayFullPageForItem`, `passwordField`-Makro, `Profile::showLegend` in beiden Versionen.
- Abdeckung von `Migration::renameItemtype` (Spalten, JSON-Felder, gleicher Tabellenname).
- Ob `state_types`, `globalsearch_types` und alle `registerClass`-Schlüssel in 12 noch existieren.
- Docker-Image-Tags für GLPI 11.0.x / 12.0.x.

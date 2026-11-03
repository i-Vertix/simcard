# simcard 2.0.0 (GLPI 12) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Das Plugin `simcard` als Version 2.0.0 auf GLPI 12 portieren: Namespace-Klassen in `src/`, Twig-Oberfläche, Migration-API, verschlüsselte PIN/PUK, Upgrade-Pfad von 1.3.1.

**Architecture:** Erst ein mechanischer Port in `src/` (`GlpiPlugin\Simcard\…`), der auf GLPI 12 installierbar ist und die harten Brüche behebt. Danach werden Install/Upgrade, das Formular, die Verschlüsselung und der Verknüpfungs-Tab einzeln ersetzt. Jede Aufgabe endet mit einem lauffähigen Plugin in der Docker-Umgebung.

**Tech Stack:** PHP ≥ 8.3, GLPI 12.0.x (Twig, `Migration`, `GLPIKey`, `DBmysql::request`), MariaDB, Docker Compose (offizielle `glpi/glpi`-Images).

**Spec:** `docs/superpowers/specs/2026-11-02-glpi12-modernization-design.md`. Beide Dokumente zusammen lesen; Abschnittsnummern wie „Spec §5.2“ beziehen sich darauf.

## Global Constraints

- `PLUGIN_SIMCARD_VERSION = '2.0.0'`, `PLUGIN_SIMCARD_MIN_GLPI = '12.0.0'`, `PLUGIN_SIMCARD_MAX_GLPI = '12.0.99'`; `requirements.php.min = '8.3'`.
- Namespace `GlpiPlugin\Simcard`, PSR-4 auf `src/`, Dateiname = Klassenname (Groß-/Kleinschreibung exakt).
- Tabellennamen bleiben unverändert (`glpi_plugin_simcard_simcards`, `…_simcards_items`, `…_simcardsizes`, `…_simcardvoltages`, `…_simcardtypes`, `…_phoneoperators`).
- Recht: Name `simcard:simcard`, Zusatzbit `SIMCARD_ASSOCIATE_TICKET = 128`.
- Kein `include('../../../inc/includes.php')`, kein `$DB->query()`, kein `Html::displayErrorAndDie()`, kein `Toolbox::addslashes_deep()`, kein `global $LANG`, kein `array()`-Literal in neuem Code.
- DB-Zugriff nur über `$DB->request([...])`, `$DB->insert/update/delete`, `Migration`-API; String-SQL nur für DDL.
- Übersetzungen: Domain `simcard` (`__('…', 'simcard')`, `_n(…, 'simcard')`); GLPI-Core-Strings ohne Domain.
- Overrides von Core-Methoden übernehmen exakt die GLPI-12-Signatur. Typisiert sind dort u. a. `canCreate/canView/canUpdate/canDelete/canPurge(): bool`, `getSectorizedDetails(): array`, `displayFullPageForItem($id, ?array $menus = null, array $options = []): void`, `countElementsInTable(string|array, array): int`, `getAllDataFromTable(string, array, bool): array`; alle übrigen (z. B. `showForm($ID, array $options = [])`, `defineTabs($options = [])`, `getTabNameForItem(CommonGLPI $item, $withtemplate = 0)`, `displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)`, `rawSearchOptions()`, `getRights($interface = 'central')`, `getSpecificMassiveActions($checkitem = null)`, `delete(array $input, $force = false, $history = true)`) bleiben ohne Typen.
- Keine automatisierte Test-Suite (Spec §8). Jede Aufgabe wird mit `php -l` im GLPI-12-Container, `bin/console`-Befehlen, SQL-Abfragen und Browser-Checks in der Docker-Umgebung verifiziert. Nach jeder Aufgabe: `files/_log/php-errors.log` und `glpi.log` ohne Einträge, die `simcard` oder `GlpiPlugin\Simcard` enthalten.
- Commit-Nachrichten enden mit `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **GLPI-Schlüssel nicht lesbar beim Upgrade:** `GLPIKey::encrypt()` gibt dann `''` zurück. Die Migration muss in diesem Fall abbrechen (Exception), statt alle PIN/PUK zu leeren. Test in Task 5.
2. **Speichern des Formulars ohne Eingabe in den PIN/PUK-Feldern:** Ein leeres Passwortfeld darf gespeicherte Werte nicht löschen; nur die Checkbox „Löschen“ leert sie. Test in Task 5.
3. **Manipuliertes `itemtype` beim Anlegen aus einem Asset (`_itemtype`/`_items_id` bzw. `itemtype` im Verknüpfungsformular):** Der heutige Code macht `new $options['itemtype']`. Nur Typen aus `Simcard_Item::getClasses()` sind zulässig, alles andere wird ignoriert bzw. abgelehnt. Test in Task 4 und Task 6.
4. **Upgrade läuft zweimal oder nach abgebrochenem Lauf erneut:** `renameItemtype` und die Verschlüsselung dürfen nichts doppelt umbenennen oder doppelt verschlüsseln. Test in Task 3 und Task 5.
5. **Rechte-Sync für ein anderes als das aktive Profil:** Der heutige Hook synchronisiert `helpdesk_item_type` nur, wenn das gerade aktive Profil geändert wird. Erwartet wird: jedes geänderte Profil wird synchronisiert, die Session nur beim aktiven. Test in Task 2.

---

### Task 1: Docker-Entwicklungsumgebung

**Files:**
- Create: `tools/docker/docker-compose.yml`
- Create: `tools/docker/README.md`
- Create: `tools/docker/make-legacy-plugin.sh`
- Create: `.gitattributes`
- Modify: `.gitignore`

**Interfaces:**
- Produces: Services `db` (`mariadb:11.4`), `glpi12` (`glpi/glpi:12.0.1`, Port `8012`), `glpi11` (`glpi/glpi:11.0.12`, Port `8011`, Profil `legacy`). `glpi12` mountet das Repo-Root nach `/var/www/glpi/plugins/simcard`; `glpi11` mountet `tools/docker/.legacy/simcard` (simcard 1.3.1). Beide teilen sich das DB-Volume und das GLPI-Daten-Volume, damit der Upgrade-Pfad 11 → 12 nachgestellt werden kann. Alle späteren Tasks rufen Befehle als `docker compose -f tools/docker/docker-compose.yml exec glpi12 …` auf (im Folgenden kurz `dc exec glpi12 …`).

- [ ] **Step 1: Mount-Pfade, Umgebungsvariablen und Install-Verhalten der Images auf Docker Hub (`glpi/glpi`) nachlesen** und die Compose-Datei danach ausrichten (Plugin-Pfad im Image, `GLPI_DB_*`-Variablen, ob das Image die DB automatisch installiert oder `bin/console db:install` nötig ist).
- [ ] **Step 2: `make-legacy-plugin.sh` schreiben**: `git archive 456450c | tar -x -C tools/docker/.legacy/simcard` (Commit `456450c` = Release 1.3.1). `tools/docker/.legacy/` in `.gitignore`.
- [ ] **Step 3: `docker-compose.yml` und `README.md` schreiben.** README beschreibt zwei Abläufe: (a) frisches GLPI 12 (`up glpi12`), (b) Upgrade-Pfad: `--profile legacy up glpi11` → simcard 1.3.1 installieren/aktivieren und Testdaten anlegen → `glpi11` stoppen → `up glpi12` → `bin/console db:update` → simcard 2.0.0 installieren/aktivieren.
- [ ] **Step 4: `.gitattributes`** mit `tools/ export-ignore`, `docs/ export-ignore`, `.gitattributes export-ignore`, `.gitignore export-ignore`.
- [ ] **Step 5: Verifizieren**

Run: `docker compose -f tools/docker/docker-compose.yml up -d glpi12` und dann `dc exec glpi12 php bin/console --version`
Expected: Ausgabe enthält `12.0.1`; `http://localhost:8012` zeigt die GLPI-Anmeldeseite.

Run: `docker compose -f tools/docker/docker-compose.yml --profile legacy up -d glpi11` (vorher `glpi12` stoppen) und `dc exec glpi11 php bin/console plugin:install simcard -u glpi && dc exec glpi11 php bin/console plugin:activate simcard`
Expected: beides erfolgreich; das ist die Ausgangsbasis für den Upgrade-Test.

- [ ] **Step 6: Commit** `git add tools/docker .gitattributes .gitignore && git commit -m "Add Docker dev environment for GLPI 12 (+ GLPI 11 for upgrade tests)"`

---

### Task 2: Namespace-Port nach `src/` (mechanisch, GLPI-12-lauffähig)

Ziel: alle Klassen liegen namespaced in `src/`, `inc/` ist weg, das Plugin lässt sich auf frischem GLPI 12 installieren und bedienen. Formulare bleiben in dieser Aufgabe echo-basiert (werden in Task 4/6 ersetzt), aber alle Feldwerte werden mit `htmlescape()` ausgegeben. Install/Upgrade wird nur verschoben, nicht neu geschrieben (das macht Task 3).

**Files:**
- Create: `composer.json`, `src/Simcard.php`, `src/Simcard_Item.php`, `src/SimcardSize.php`, `src/SimcardVoltage.php`, `src/SimcardType.php`, `src/PhoneOperator.php`, `src/Profile.php`, `src/SimcardInjection.php`
- Modify: `setup.php`, `hook.php`, `front/simcard.php`, `front/simcard.form.php`, `front/simcard_item.form.php`
- Delete: `inc/` (alle Dateien)

**Interfaces:**
- Produces:
  - `GlpiPlugin\Simcard\Profile`: `const RIGHT_SIMCARD_SIMCARD = 'simcard:simcard'`, `const SIMCARD_ASSOCIATE_TICKET = 128`, `public static function createFirstAccess(int $profiles_id): void`, `public static function getAllRights(): array`.
  - `GlpiPlugin\Simcard\Simcard`: `public static $rightname = Profile::RIGHT_SIMCARD_SIMCARD`, `getTypeName($nb = 0)`, `getIcon()` → `'ti ti-device-sim'`.
  - `GlpiPlugin\Simcard\Simcard_Item`: `public static $itemtype_1 = Simcard::class`, `public static function getClasses(): array`, `public static function registerItemtype(string $itemtype): void`, `public static function showForSimcard(Simcard $simcard): void`, `public static function showForItem(CommonDBTM $item): void`.
  - Dropdowns (`SimcardSize`, `SimcardVoltage`, `SimcardType`, `PhoneOperator`): `public static function transfer(int $ID, int $entity): int`.
  - `setup.php`: Konstante `PLUGIN_SIMCARD_LEGACY_CLASSES` (array alt ⇒ neu, die acht Zeilen aus Spec §3) und ein `spl_autoload_register`, der beim Zugriff auf einen alten Namen `class_alias(neu, alt)` ausführt (lazy, lädt nicht alle Klassen bei jedem Request).
  - Die statischen `install/upgrade/uninstall`-Methoden der Klassen bleiben vorerst erhalten (nur namespaced); Task 3 ersetzt sie.

- [ ] **Step 1: `composer.json`** mit `"autoload": {"psr-4": {"GlpiPlugin\\Simcard\\": "src/"}}`, `"require": {"php": ">=8.3"}`; kein `vendor/` nötig (GLPI lädt `src/` selbst), daher `vendor/` nicht committen.
- [ ] **Step 2: Klassen nach `src/` verschieben** (`git mv` pro Datei, damit die History erhalten bleibt), umbenennen, `namespace GlpiPlugin\Simcard;` plus `use`-Statements für Core-Klassen. Alle Strings `'PluginSimcard…'` durch `::class`-Konstanten ersetzen. `can*()`-Overrides mit `: bool`. `global $LANG` und `array()` entfernen. `getMenuContent()` aus `Simcard` löschen (GLPIs Standard erzeugt Such-, Hinzufügen- und Vorlagen-Links bereits, siehe `CommonGLPI::getMenuContent`).
- [ ] **Step 3: Brüche aus Spec §2 beheben:** `transfer()` in allen vier Dropdowns über `getFromDB($ID)` + `import(['name' => …, 'entities_id' => $entity])`, ohne Roh-SQL und ohne `addslashes_deep` (P2). In `Simcard_Item::showForItem()` die String-Query durch `$DB->request([...])` ersetzen. `countElementsInTable()`-Aufruf auf die Kriterien-Array-Form ohne `'WHERE'`-Schlüssel umstellen.
- [ ] **Step 4: Echo-Formulare escapen:** jeder ausgegebene Feldwert über `htmlescape()` (P4 vorläufig). `new $options['itemtype']` in `Simcard::showForm()` nur ausführen, wenn `in_array($options['itemtype'], Simcard_Item::getClasses(), true)` (Review Focus 3).
- [ ] **Step 5: `setup.php` neu schreiben** nach Spec §7: Konstanten, `plugin_init_simcard(): void`, `plugin_version_simcard(): array` mit `requirements`, `plugin_simcard_check_config(bool $verbose = false): bool`. Hooks über `Glpi\Plugin\Hooks`-Konstanten. `Plugin::registerClass(Simcard::class, [...])` mit allen Schlüsseln aus Spec §4 **inklusive** `state_types` und `globalsearch_types` (in GLPI 12 vorhanden, ersetzt die direkten `$CFG_GLPI[...][]`-Zuweisungen). Entfernen: `csrf_compliant`, `headings*`, `migratetypes`, `minGlpiVersion`, `plugin_simcard_check_prerequisites`, `plugin_datainjection_migratetypes_simcard`. Neu: `$PLUGIN_HOOKS[Hooks::SECURED_FIELDS]['simcard'] = ['glpi_plugin_simcard_simcards.pin', 'glpi_plugin_simcard_simcards.pin2', 'glpi_plugin_simcard_simcards.puk', 'glpi_plugin_simcard_simcards.puk2']` (wirkt ab Task 5, schadet vorher nicht).
- [ ] **Step 6: `hook.php` anpassen:** `include_once`-Zeilen entfernen; Funktionssignaturen typisieren: `plugin_simcard_install(): bool`, `plugin_simcard_uninstall(): bool`, `plugin_simcard_getDatabaseRelations(): array`, `plugin_simcard_getDropdown(): array`, `plugin_simcard_AssignToTicket(array $types): array`, `plugin_simcard_forceGroupBy(string $itemtype): bool`, `plugin_simcard_getAddSearchOptions(string $itemtype): array`, `plugin_item_purge_simcard(CommonDBTM $item): void`, `plugin_datainjection_populate_simcard(): void`, `plugin_simcard_postinit(): void`, `plugin_simcard_profileRightUpdate(ProfileRight $item): void`. In `getAddSearchOptions` `__s`/`_sn` durch `__`/`_n` ersetzen (Such-Optionsnamen werden von GLPI selbst escaped). `profileRightUpdate`: Profil immer synchronisieren; Session nur anpassen, wenn `$item->fields['profiles_id'] == $_SESSION['glpiactiveprofile']['id']` (Review Focus 5). itemtype-String überall `Simcard::class`.
- [ ] **Step 7: Controller entschärfen** (vollständige Überarbeitung in Task 4/6): `include(...)`-Zeile entfernen; `front/simcard_item.form.php` wirft am Ende `new \Glpi\Exception\Http\BadRequestHttpException()` statt `displayErrorAndDie` (P3); `front/simcard.php` ruft `Session::checkRight(Simcard::$rightname, READ)` statt der eigenen Access-denied-Ausgabe.
- [ ] **Step 8: Syntax prüfen**

Run: `dc exec glpi12 sh -c 'cd plugins/simcard && for f in setup.php hook.php src/*.php front/*.php; do php -l "$f" || exit 1; done'`
Expected: jede Zeile `No syntax errors detected`.

- [ ] **Step 9: Frische Installation prüfen**

Run: `dc exec glpi12 php bin/console plugin:install simcard -u glpi && dc exec glpi12 php bin/console plugin:activate simcard`
Expected: beide erfolgreich.

Browser (`http://localhost:8012`, Konto aus dem Image): *Assets → SIM-Karten* erscheint; SIM-Karte mit Name `O'Brien <b>x</b>` anlegen → Name wird wörtlich angezeigt, nicht fett; Typ/Größe/Spannung/Provider auswählbar; Verknüpfung mit einem Computer anlegen und wieder lösen; Massenaktion „Entität übertragen“ auf eine Unter-Entität läuft ohne Fehler.

- [ ] **Step 10: P1 prüfen.** Einen Computer mit verknüpfter SIM-Karte endgültig löschen (löst `getDbRelations()` aus). Danach `dc exec glpi12 grep -c "Invalid relations declared" /var/glpi/logs/php-errors.log` (Pfad gemäß Task 1).
Expected: `0` bzw. Datei ohne diese Zeile.

- [ ] **Step 11: Rechte-Sync prüfen (Review Focus 5).** Als Super-Admin das Profil *Technician* (nicht aktiv) öffnen, im SIM-Karten-Tab „Associable to a ticket“ setzen und speichern. `dc exec db mariadb` (Zugangsdaten aus `docker-compose.yml`) mit `"SELECT helpdesk_item_type FROM glpi_profiles WHERE name='Technician'"`
Expected: JSON enthält `GlpiPlugin\\Simcard\\Simcard`. Häkchen entfernen → Eintrag verschwindet.

- [ ] **Step 12: Commit** `git add -A setup.php hook.php composer.json src front inc && git commit -m "Port plugin classes to GlpiPlugin\Simcard namespace for GLPI 12"`

---

### Task 3: Install / Upgrade / Uninstall über `Install`-Klasse

**Files:**
- Create: `src/Install.php`
- Modify: `hook.php` (`plugin_simcard_install`, `plugin_simcard_uninstall`), alle `src/*.php` (statische `install/upgrade/uninstall` entfernen)

**Interfaces:**
- Consumes: `PLUGIN_SIMCARD_LEGACY_CLASSES` (Task 2), `Profile::createFirstAccess()`.
- Produces: `GlpiPlugin\Simcard\Install` mit
  - `public static function install(Migration $migration): void`
  - `public static function upgrade(Migration $migration, string $from): void`
  - `public static function uninstall(Migration $migration): void`
  - `plugin_simcard_install()` liest `$from` vor dem Aufruf: `$plugin = new Plugin(); $plugin->getFromDBbyDir('simcard'); $from = $plugin->fields['version'] ?? '0.0.0';` und ruft `install()` wenn `glpi_plugin_simcard_simcards` fehlt, sonst `upgrade()`. Danach `$migration->executeMigration()`.

- [ ] **Step 1: `install()`** legt die sechs Tabellen mit denselben Spalten und Defaults wie das bisherige DDL an (`inc/*.class.php` in Commit `456450c` ist die Referenz), über `CREATE TABLE` per `$DB->doQuery()` mit `DBConnection::getDefaultCharset()/getDefaultCollation()/getDefaultPrimaryKeySignOption()` (GLPI 12 hat kein `Migration::createTable`). Ausnahme: **keine** Indizes auf `pin`, `pin2`, `puk`, `puk2`. Dropdown-Defaults per `$DB->insert` (Größen `Full-SIM`, `Micro-SIM`, `Mini-SIM`, `Nano-SIM`; Spannungen `3V`, `5V`; Typen wie bisher). Display-Prefs `$migration->updateDisplayPrefs([Simcard::class => [1, 412, 80, 411, 410]], [], true)`. `Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id'])` nur, wenn eine Session existiert (bei `bin/console` gibt es keine).
- [ ] **Step 2: `upgrade()`** in dieser Reihenfolge:
  1. für jedes Paar aus `PLUGIN_SIMCARD_LEGACY_CLASSES`: `$migration->renameItemtype($alt, $neu, false)`;
  2. `glpi_profiles.helpdesk_item_type` (JSON-Array) für alle Profile: `'PluginSimcardSimcard'` durch `Simcard::class` ersetzen, Duplikate entfernen, per `$DB->update` speichern;
  3. `$migration->dropTable('glpi_plugin_simcard_configs')` und `…_profiles`, wenn vorhanden.

  Task 5 hängt danach den Verschlüsselungsschritt an.
- [ ] **Step 3: `uninstall()`** nach Spec §5.4: für jedes neue **und** alte Klassen-Item `deleteByCriteria(['itemtype' => …])` auf `DisplayPreference`, `SavedSearch`, `DropdownTranslation`, `Item_Ticket`, `Item_Problem`, `Document_Item`, `Infocom`, `Contract_Item`, `Log`, `Notepad`, `ReservationItem`, `NetworkPort`; `ProfileRight` mit `name = 'simcard:simcard'` löschen; `helpdesk_item_type` bereinigen; Datainjection-Modelle wie bisher (nur wenn Plugin aktiv); dann Tabellen droppen.
- [ ] **Step 4: Syntax prüfen** (Befehl aus Task 2 Step 8). Expected: keine Fehler.
- [ ] **Step 5: Frische Installation + Deinstallation**

Run: `dc exec glpi12 php bin/console plugin:uninstall simcard && dc exec glpi12 php bin/console plugin:install simcard -u glpi && dc exec glpi12 php bin/console plugin:activate simcard`
Expected: erfolgreich; `SHOW INDEX FROM glpi_plugin_simcard_simcards WHERE Column_name LIKE 'pin%' OR Column_name LIKE 'puk%'` liefert 0 Zeilen; `SELECT name FROM glpi_plugin_simcard_simcardsizes` liefert die vier Größen.

Run: `dc exec glpi12 php bin/console plugin:uninstall simcard` und `SHOW TABLES LIKE 'glpi_plugin_simcard%'`; `SELECT COUNT(*) FROM glpi_profilerights WHERE name='simcard:simcard'`
Expected: keine Tabellen, `0`.

- [ ] **Step 6: Upgrade-Pfad 1.3.1 → 2.0.0** (Ablauf (b) aus `tools/docker/README.md`). Testdaten auf GLPI 11 mit simcard 1.3.1: eine SIM-Karte mit Ticket-Verknüpfung, Dokument, Infocom, Computer-Verknüpfung, eigener Display-Preference, Historie. Danach Core-Update auf 12, `plugin:install simcard -u glpi`, `plugin:activate simcard`.
Expected:
  - `SELECT COUNT(*) FROM glpi_items_tickets WHERE itemtype='PluginSimcardSimcard'` → `0`; mit `itemtype='GlpiPlugin\\Simcard\\Simcard'` → `1`. Analog `glpi_documents_items`, `glpi_infocoms`, `glpi_logs`, `glpi_displaypreferences`.
  - Im Browser: SIM-Karte öffnen, Tabs Tickets/Dokumente/Infocom/Historie zeigen die Altdaten.
- [ ] **Step 7: Idempotenz (Review Focus 4).** `plugin:install simcard -u glpi --force` erneut ausführen.
Expected: erfolgreich, dieselben Zählwerte wie in Step 6.
- [ ] **Step 8: Commit** `git commit -am "Move install/upgrade/uninstall to Install class with itemtype migration"` (inkl. `git add src/Install.php`).

---

### Task 4: Twig-Hauptformular und Controller

**Files:**
- Create: `templates/simcard.html.twig`
- Modify: `src/Simcard.php` (`showForm`), `front/simcard.form.php`, `front/simcard.php`

**Interfaces:**
- Consumes: `Simcard_Item::getClasses()` (Task 2).
- Produces: `Simcard::showForm($ID, array $options = []): bool` rendert `@simcard/simcard.html.twig` über `TemplateRenderer::getInstance()->display()` mit `item`, `params` (= `$options`), `linked_item` (CommonDBTM|null, nur wenn `_itemtype` in `getClasses()`), `can_see_secrets` (bool = `Simcard::canUpdate()`). PIN/PUK werden in dieser Aufgabe als `passwordField` mit dem Rohwert befüllt; Task 5 schaltet auf Entschlüsselung um.

- [ ] **Step 1: Template schreiben:** `{% extends 'generic_show_form.html.twig' %}`, `{% import 'components/form/fields_macros.html.twig' as fields %}`, Block `more_fields`. Felder und Reihenfolge wie bisher (Spec §4): `textField` name, `dropdownField` `State` (`states_id`), `Location`, `SimcardType`, `User` (`users_id_tech`, `right: 'all'`), `SimcardSize`, `Group` (`groups_id_tech`, `condition: {'is_assign': 1}`), `SimcardVoltage`, `PhoneOperator`, `yesNoField` `is_helpdesk_visible`, `User` (`users_id`, `right: 'all'`), `textField` otherserial, `Group` (`groups_id`), `textField` phonenumber, `textField` serial (Label „IMSI“), `textareaField` comment. Hidden `is_global = 1`. Bei `linked_item`: Zeile „Associated element“ mit `linked_item.getLink()` und Hidden-Felder `_itemtype`/`_items_id`. Abschnitt PIN/PUK (`passwordField` mit `is_disclosable: true`, `clearable: false`) nur `{% if can_see_secrets %}`.
- [ ] **Step 2: `showForm` umstellen** auf das Template; den echo-Code löschen.
- [ ] **Step 3: `front/simcard.form.php`** auf das Standardmuster: `add` (`check(-1, CREATE, $_POST)`), `delete` (`check($id, DELETE)`), `restore` (`check($id, DELETE)`), `purge` (`check($id, PURGE)`), `update` (`check($id, UPDATE)`), jeweils mit `Event::log` wie bisher; sonst `Simcard::displayFullPageForItem($_GET['id'] ?? 0, ['assets', Simcard::class], ['withtemplate' => $_GET['withtemplate'] ?? ''])` (P5). `unglobalize`-Zweig entfällt.
- [ ] **Step 4: `front/simcard.php`**: `Session::checkRight(Simcard::$rightname, READ)`, `Html::header(Simcard::getTypeName(Session::getPluralNumber()), '', 'assets', Simcard::class)`, `Search::show(Simcard::class)`, `Html::footer()`.
- [ ] **Step 5: Syntax prüfen** (Task 2 Step 8).
- [ ] **Step 6: Browser-Checks**
Expected:
  - Neue SIM-Karte über *Hinzufügen* und über *Vorlage* anlegen; alle Felder speichern und erscheinen nach Reload.
  - Name `O'Brien <script>alert(1)</script>`: kein Alert, Text wörtlich sichtbar.
  - Benutzer mit nur READ-Recht: PIN/PUK-Abschnitt fehlt; Aufruf von `front/simcard.form.php?id=<id>` ohne READ-Recht → 403-Seite.
  - Aus dem SIM-Karten-Tab eines Computers „neue SIM-Karte“ anlegen: „Associated element“ zeigt den Computer, nach dem Speichern ist die Verknüpfung da.
  - **Review Focus 3:** `front/simcard.form.php?_itemtype=User&_items_id=2` aufrufen → keine „Associated element“-Zeile, kein Fehler.
- [ ] **Step 7: Commit** `git add templates src/Simcard.php front && git commit -m "Render SIM card form with Twig and use standard form controller"`

---

### Task 5: PIN/PUK-Verschlüsselung

**Files:**
- Modify: `src/Simcard.php`, `src/Install.php`, `templates/simcard.html.twig`

**Interfaces:**
- Consumes: `Install::upgrade(Migration $migration, string $from)` (Task 3), Template-Variable `can_see_secrets` (Task 4).
- Produces:
  - `Simcard::SECRET_FIELDS = ['pin', 'pin2', 'puk', 'puk2']`
  - `public function getDecryptedSecret(string $field): string` (leerer String bei leerem Wert)
  - `public array $history_blacklist = ['pin', 'pin2', 'puk', 'puk2']`
  - Formular-Konvention: Eingabefeld `pin` (leer = unverändert), Checkbox `_clear_pin` (gesetzt = leeren); gleiches Muster für `pin2`, `puk`, `puk2`.
  - Install-Schritt `private static function encryptSecrets(Migration $migration): void`.

- [ ] **Step 1: `prepareInputForAdd` / `prepareInputForUpdate`** in `Simcard`: für jedes Feld aus `SECRET_FIELDS`: ist `_clear_<feld>` gesetzt → `''`; sonst nicht-leerer Wert → `(new GLPIKey())->encrypt($wert)`; leerer Wert beim Update → Schlüssel aus `$input` entfernen (unverändert lassen). Gibt `encrypt()` `''` für einen nicht-leeren Wert zurück, Fehlermeldung über `Session::addMessageAfterRedirect(..., false, ERROR)` und `return false`.
- [ ] **Step 2: Template** übergibt `item.getDecryptedSecret('pin')` usw. an die `passwordField`s und rendert je eine Checkbox `_clear_<feld>` („Clear“).
- [ ] **Step 3: `rawSearchOptions`**: Optionen 413–416 entfernen.
- [ ] **Step 4: `Install::encryptSecrets()`**, aufgerufen in `upgrade()` nur bei `version_compare($from, '2.0.0', '<')`, nach dem itemtype-Schritt:
  - `$key = new GLPIKey(); if ($key->get() === null) throw new RuntimeException(...)` (Review Focus 1), Meldung nennt `glpicrypt.key`.
  - Alle Zeilen mit mindestens einem nicht-leeren Geheimfeld per `$DB->request`, jedes nicht-leere Feld mit `$key->encrypt()` neu schreiben per `$DB->update` (keine History).
  - `$migration->dropKey($table, 'pin')` / `pin2` / `puk` / `puk2`.
  - `$migration->updateDisplayPrefs([], [Simcard::class => [413, 414, 415, 416]])`.
- [ ] **Step 5: Syntax prüfen** (Task 2 Step 8).
- [ ] **Step 6: Laufzeit-Checks (Review Focus 2)**
Expected:
  - PIN `1234` speichern → `SELECT pin FROM glpi_plugin_simcard_simcards WHERE id=<id>` zeigt Base64-Chiffretext, nicht `1234`; Formular zeigt nach Klick auf „Anzeigen“ `1234`.
  - Formular ohne Eingabe im PIN-Feld speichern (z. B. nur Kommentar ändern) → PIN bleibt `1234`.
  - Checkbox „Clear“ bei PIN → PIN leer.
  - Historie-Tab: Änderung von PIN erzeugt keinen Log-Eintrag mit Wert.
- [ ] **Step 7: Upgrade-Check** (Upgrade-Pfad aus Task 3 Step 6 neu durchlaufen, Testdaten mit PIN `1111`, PUK `22222222`, PIN2 leer).
Expected: DB enthält Chiffretext für `pin`/`puk`, `pin2` bleibt `''`; Formular zeigt `1111`/`22222222`; vorhandene Log-Einträge aus 1.3.1 unverändert (`SELECT COUNT(*) FROM glpi_logs WHERE itemtype='GlpiPlugin\\Simcard\\Simcard'` gleich wie vor dem Upgrade).
- [ ] **Step 8: Idempotenz.** `plugin:install simcard -u glpi --force` erneut.
Expected: PIN im Formular weiterhin `1111` (nicht doppelt verschlüsselt, da `$from` jetzt `2.0.0`).
- [ ] **Step 9: Schlüssel fehlt (Review Focus 1).** Upgrade-Pfad erneut aufsetzen, vor dem Plugin-Upgrade `glpicrypt.key` im Config-Volume umbenennen, `plugin:install simcard -u glpi` ausführen.
Expected: Befehl schlägt mit der Meldung zu `glpicrypt.key` fehl; `SELECT pin FROM glpi_plugin_simcard_simcards` zeigt weiterhin `1111`. Schlüssel zurückbenennen.
- [ ] **Step 10: Commit** `git commit -am "Encrypt SIM PIN/PUK with GLPIKey and migrate existing values"`

---

### Task 6: Verknüpfungs-Tab mit Twig

**Files:**
- Create: `templates/simcard_item.html.twig`
- Modify: `src/Simcard_Item.php`, `front/simcard_item.form.php`

**Interfaces:**
- Consumes: `Simcard_Item::getClasses()`, `Simcard_Item::showForSimcard(Simcard $simcard): void`, `Simcard_Item::showForItem(CommonDBTM $item): void` (Task 2).
- Produces: Template-Variablen `can_edit` (bool), `form_mode` (`'simcard'` | `'item'`), `simcard` / `item`, `used_simcards` (int[]), `entity_restrict` (int), `datatable` (Array für `components/datatable.html.twig` mit `columns` `type`, `entity`, `name`, `serial`, `otherserial`, `entries`, `showmassiveactions`, `massiveactionparams` mit `container` und `itemtype => Simcard_Item::class`).

- [ ] **Step 1: Template schreiben:** oben das Hinzufügen-Formular (`form_mode == 'simcard'`: `Dropdown::showSelectItemFromItemtypes` mit `itemtypes = getClasses()`, `entity_restrict`; `form_mode == 'item'`: `dropdownField` `Simcard` mit `used`), darunter `{% include 'components/datatable.html.twig' with datatable %}`. Formular-Action `Simcard_Item::getFormURL()`, Button-Name `additem`.
- [ ] **Step 2: `showForSimcard`/`showForItem`** sammeln die Zeilen per `$DB->request([...])` und rufen das Template auf; echo-Code entfernen. Name in `entries` als `$linked->getLink()` mit Spalte `name` in `formatters` als `raw_html`; alle anderen Spalten Klartext.
- [ ] **Step 3: `front/simcard_item.form.php`:** `additem` → `itemtype` muss in `getClasses()` sein, sonst `BadRequestHttpException` (Review Focus 3); `check(-1, CREATE, $_POST)`, `add()`, `Event::log`, `Html::back()`. Das Trennen läuft über die Standard-Massenaktion `purge` des datatable; der `delete_items`-Zweig entfällt.
- [ ] **Step 4: Syntax prüfen** (Task 2 Step 8).
- [ ] **Step 5: Browser-Checks**
Expected:
  - SIM-Karte → Tab „Verknüpfte Elemente“: Computer, Drucker, Telefon hinzufügen; Liste zeigt Typ, Entität, Name-Link, Seriennummer, Inventarnummer.
  - Zwei markieren → Massenaktion „Endgültig löschen“ → beide Verknüpfungen weg, die Assets selbst existieren noch.
  - Computer → Tab „SIM-Karten“: bereits verknüpfte SIM-Karte ist im Dropdown nicht wählbar; Hinzufügen funktioniert.
  - Computer mit Verknüpfung endgültig löschen → `SELECT COUNT(*) FROM glpi_plugin_simcard_simcards_items WHERE itemtype='Computer' AND items_id=<id>` = `0`.
  - **Review Focus 3:** POST an `front/simcard_item.form.php` mit `additem=1&itemtype=User&items_id=2&plugin_simcard_simcards_id=<id>` (über die Browser-Konsole per `fetch` mit dem Formular-Token) → HTTP 400, kein neuer Datensatz.
- [ ] **Step 6: Commit** `git add templates/simcard_item.html.twig src/Simcard_Item.php front/simcard_item.form.php && git commit -m "Render SIM card links tab with Twig datatable"`

---

### Task 7: Übersetzungen, Doku, Release-Metadaten

**Files:**
- Modify: `locales/simcard.pot`, `locales/*.po`, `locales/*.mo`, `README.md`, `CLAUDE.md`
- Create: `CHANGELOG.md`

- [ ] **Step 1: Neue/geänderte Strings** (`Clear`, Fehlermeldung zu `glpicrypt.key` u. a.) als neue `msgid`s von Hand in `locales/simcard.pot` ergänzen und mit `msgmerge -U locales/<lang>.po locales/simcard.pot` in alle `.po` übernehmen; Deutsch übersetzen, andere Sprachen leer lassen.
- [ ] **Step 2: `.mo` kompilieren:** `for f in locales/*.po; do msgfmt "$f" -o "${f%.po}.mo"; done`. Expected: keine Fehler.
- [ ] **Step 3: `CHANGELOG.md`** mit Abschnitt `2.0.0`: GLPI 12 only, PHP 8.3, Namespace-Umstellung inkl. automatischer itemtype-Migration, PIN/PUK-Verschlüsselung (nicht mehr durchsuchbar), behobene Probleme P1–P9, Upgrade-Pfad aus Spec §1.
- [ ] **Step 4: `README.md`**: Kompatibilitätstabelle (1.3.x → GLPI 10/11, 2.0.x → GLPI 12) und Upgrade-Anleitung. **`CLAUDE.md`** (derzeit untracked; der Nutzer entscheidet über das Committen): Architekturabschnitt auf `src/`, `templates/`, `Install`-Klasse, GLPI 12 und Docker-Umgebung aktualisieren.
- [ ] **Step 5: Commit** `git add locales README.md CHANGELOG.md && git commit -m "Update translations and docs for 2.0.0"`

---

### Task 8: Abnahme auf GLPI 12

**Files:** keine (nur Verifikation; gefundene Fehler werden in der zuständigen Task-Datei behoben und separat committet).

- [ ] **Step 1: Logs leeren**, Volumes neu aufsetzen (`docker compose … down -v`).
- [ ] **Step 2: Komplette Checkliste aus Spec §8.3** durchgehen, sowohl auf frischer Installation als auch nach dem Upgrade-Pfad 11 + 1.3.1 → 12 + 2.0.0.
- [ ] **Step 3: Release-Archiv prüfen:** `git archive --format=tar.gz --prefix=simcard/ HEAD -o /tmp/simcard-2.0.0.tar.gz && tar -tzf /tmp/simcard-2.0.0.tar.gz`
Expected: enthält `setup.php`, `hook.php`, `src/`, `templates/`, `front/`, `locales/`, `composer.json`, `README.md`, `CHANGELOG.md`, `LICENSE`; enthält **nicht** `tools/`, `docs/`, `inc/`.
- [ ] **Step 4: Logs prüfen:** `php-errors.log` und `glpi.log` ohne Einträge zu `simcard`/`GlpiPlugin\Simcard`.
- [ ] **Step 5: Ergebnis** (bestanden / Fehlerliste) dem Nutzer berichten.

# Changelog

## 2.0.0

**Requirements:** GLPI 12.0.0 to 12.0.99 only, PHP 8.3 or newer. GLPI 10 and 11 stay on the 1.3.x line.

### Changed

- All classes are namespaced and autoloaded from `src/` (`GlpiPlugin\Simcard\Simcard`, `Simcard_Item`,
  `SimcardSize`, `SimcardVoltage`, `SimcardType`, `PhoneOperator`, `Profile`, `SimcardInjection`).
  The old `PluginSimcard*` class names remain available as deprecated aliases.
  Upgrading migrates the itemtypes stored in the database (tickets, logs, documents, infocoms, display
  preferences, saved searches, reservations, profile `helpdesk_item_type`, ...) automatically.
- The SIM card form and the links tab are rendered with Twig.
- Install, upgrade and uninstall are handled by the `Install` class. The upgrade steps are not versioned: they run
  on every update and are idempotent. The one-time PIN/PUK encryption is guarded by the config marker
  `plugin:simcard/secrets_encrypted`.
- PIN 1/2 and PUK 1/2 are encrypted with the GLPI key (`GLPIKey`) and existing values are encrypted during the upgrade.
  They are no longer searchable, are no longer available in Data Injection mappings, and their values are no
  longer written to the item history. An empty field on the form keeps the stored code, the "Clear" checkbox empties it.
  History rows written by 1.x are left unchanged: they keep the plaintext PIN/PUK values and remain visible to
  every user with read access to the item history. Administrators who need to remove them must clean `glpi_logs`
  themselves.

### Fixed

- P1: `Invalid relations declared between "glpi_plugin_simcard_simcardtypes" and "glpi_plugin_simcard_simcards"`
  warning (e.g. during inventory import), caused by legacy class file names (SimcardType, SimcardSize, SimcardVoltage, PhoneOperator).
- P2: the "Transfer to another entity" massive action failed with a fatal error on GLPI 12.
- P3: `front/simcard_item.form.php` failed with a fatal error on an unknown action; it now answers with a bad request.
- P4: XSS / broken values with apostrophes in the form; fields are now escaped by the Twig field macros.
- P5: missing rights check when displaying `front/simcard.form.php`.
- P6: PIN/PUK stored in plaintext in the database and written to the history. New history rows no longer contain
  them; history rows written by 1.x still do (see above).
- P7: every upgrade ran `ALTER TABLE ... ENGINE=InnoDB` on all tables.
- P8: signature incompatibilities with GLPI 12 (native types of `CommonGLPI`/`CommonDBTM` methods, changed DB helper signatures).
- P9: dead or broken hooks (`headings*`, `migratetypes` with a wrong class name, `csrf_compliant`).

### Removed

- The NetworkPort tab (it never rendered).
- The PIN/PUK search options 413 to 416.
- Support for GLPI 10 and 11 (use 1.3.x there).

### Upgrade path

1. GLPI 11 with simcard 1.3.1: update the GLPI core to 12 (`bin/console db:update`). simcard is deactivated by the
   update because its maximum GLPI version is lower than 12.
2. After `db:update`, GLPI suspends plugin execution. Run `bin/console plugin:resume_execution` (or the equivalent
   action in the UI) before simcard can be updated. See [tools/docker/README.md](tools/docker/README.md) for a worked example.
3. Replace the plugin files in `plugins/simcard` with 2.0.0.
4. In *Setup > Plugins* update and activate simcard (or `bin/console plugin:install simcard -u <user>` followed by
   `bin/console plugin:activate simcard`).

### Release note

If the GLPI security key is changed (`bin/console security:change_key`) while simcard is inactive, the PIN/PUK
codes are not re-encrypted and become unreadable. Activate the plugin before changing the key.

## 1.3.1

Last release for GLPI 10.0 to 11.0.

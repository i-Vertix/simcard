# Simcard Plugin for [GLPI](https://glpi-project.org/)

Adds SIM cards as an asset type to GLPI: link them to computers, phones, printers, peripherals and network
equipment, and manage them with tickets, contracts, infocoms, documents and reservations.

Original author: [GitHub Repository](https://github.com/pluginsGLPI/simcard)

Maintained by [i-Vertix](https://github.com/i-Vertix/simcard).

## Compatibility

| Plugin version | GLPI              | PHP    |
|----------------|-------------------|--------|
| 2.0.x          | 12.0.x            | >= 8.3 |
| 1.3.x          | 10.0.x to 11.0.x  | see GLPI |

Older versions are not supported.

## Installation

1. Download the release matching your GLPI version from [https://github.com/i-Vertix/simcard/releases](https://github.com/i-Vertix/simcard/releases).
2. Extract the archive into `glpi/plugins/simcard`.
3. In *Setup > Plugins* install and activate simcard.
4. If necessary, configure the rights for users *(Administration > Profiles)*.

## Upgrading from 1.3.x (GLPI 11 to GLPI 12)

1. Update the GLPI core to 12 (`bin/console db:update`). simcard 1.3.x is deactivated by the update.
2. GLPI suspends plugin execution after `db:update`. Run `bin/console plugin:resume_execution` (or the equivalent
   action in the UI) before updating simcard.
3. Replace `plugins/simcard` with the 2.0.x files.
4. Update and activate simcard in *Setup > Plugins* (or `bin/console plugin:install simcard -u <user>` and
   `bin/console plugin:activate simcard`). Itemtypes are migrated and PIN/PUK codes are encrypted automatically.

Important:

- PIN/PUK are encrypted with the GLPI key. They are no longer searchable and no longer part of Data Injection mappings.
- Do not change the GLPI security key (`security:change_key`) while simcard is inactive, otherwise the PIN/PUK
  codes become unreadable. Activate the plugin first.
- History rows written by 1.x are not changed by the upgrade: they keep the plaintext PIN/PUK values and remain
  visible to every user with read access to the item history. Administrators who need to remove them must clean
  `glpi_logs` themselves.

See [CHANGELOG.md](CHANGELOG.md) for details.

## Development

A Docker environment (GLPI 12, and GLPI 11 for upgrade tests) is described in [tools/docker/README.md](tools/docker/README.md).

## Contributing

If you want to help us keeping this plugin up-to-date, feel free to send us a pull request.

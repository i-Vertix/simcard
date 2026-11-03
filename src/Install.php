<?php
/*
 * @version $Id$
 LICENSE

  This file is part of the simcard plugin.

 Order plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Order plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; along with Simcard. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package   simcard
 @author    PGUM s.r.l, the simcard plugin team
 @copyright Copyright (c) 2019 PGUM s.r.l
 @license   GPLv2+
            http://www.gnu.org/licenses/gpl.txt
 @link      https://pgum.eu
 @link      https://github.com/pluginsglpi/simcard
 @link      http://www.glpi-project.org/
 @since     2009
 ---------------------------------------------------------------------- */

namespace GlpiPlugin\Simcard;

use Config;
use Contract_Item;
use DBConnection;
use DisplayPreference;
use Document_Item;
use DropdownTranslation;
use Fieldblacklist;
use FieldUnicity;
use GLPIKey;
use Infocom;
use Item_Problem;
use Item_Ticket;
use Log;
use Migration;
use NetworkPort;
use Notepad;
use Plugin;
use ReservationItem;
use RuntimeException;
use SavedSearch;
use Throwable;

/**
 * Install, upgrade and uninstall of the plugin.
 */
final class Install
{
    private const TABLE_SIMCARDS = 'glpi_plugin_simcard_simcards';
    private const TABLE_ITEMS = 'glpi_plugin_simcard_simcards_items';
    private const DROPDOWN_TABLES = [
        'glpi_plugin_simcard_simcardsizes',
        'glpi_plugin_simcard_simcardvoltages',
        'glpi_plugin_simcard_simcardtypes',
        'glpi_plugin_simcard_phoneoperators',
    ];

    private const CONFIG_CONTEXT = 'plugin:simcard';
    // Set once the PIN and PUK codes are stored encrypted (fresh install, or upgrade done)
    private const CONFIG_SECRETS_ENCRYPTED = 'secrets_encrypted';

    /**
     * Fresh installation.
     */
    public static function install(Migration $migration): void
    {
        self::createSchema();

        $migration->updateDisplayPrefs([
            Simcard::class => [1, 412, 80, 411, 410],
        ], [], true);

        // Fresh tables: PIN and PUK codes are encrypted from the start
        self::markSecretsEncrypted();

        // No session (and therefore no active profile) when installed with bin/console:
        // in that case no profile gets the right until an administrator grants it.
        if (isset($_SESSION['glpiactiveprofile']['id'])) {
            Profile::createFirstAccess((int) $_SESSION['glpiactiveprofile']['id']);
        }
    }

    /**
     * Create the missing tables and seed the default dropdown values.
     * Idempotent: also run on upgrade, to complete a partially failed fresh install.
     */
    private static function createSchema(): void
    {
        global $DB;

        $charset = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $sign = DBConnection::getDefaultPrimaryKeySignOption();

        if (!$DB->tableExists(self::TABLE_SIMCARDS)) {
            // No index on pin, pin2, puk, puk2: they are never searched by value
            $DB->doQuery("CREATE TABLE `" . self::TABLE_SIMCARDS . "` (
              `id` int {$sign} NOT NULL AUTO_INCREMENT,
              `entities_id` int {$sign} NOT NULL DEFAULT '0',
              `name` varchar(255) NOT NULL DEFAULT '',
              `phonenumber` varchar(255) NOT NULL DEFAULT '',
              `serial` varchar(255) NOT NULL DEFAULT '',
              `pin` varchar(255) NOT NULL DEFAULT '',
              `pin2` varchar(255) NOT NULL DEFAULT '',
              `puk` varchar(255) NOT NULL DEFAULT '',
              `puk2` varchar(255) NOT NULL DEFAULT '',
              `otherserial` varchar(255) NOT NULL DEFAULT '',
              `states_id` int {$sign} NOT NULL DEFAULT '0',
              `locations_id` int {$sign} NOT NULL DEFAULT '0',
              `users_id` int {$sign} NOT NULL DEFAULT '0',
              `users_id_tech` int {$sign} NOT NULL DEFAULT '0',
              `groups_id` int {$sign} NOT NULL DEFAULT '0',
              `groups_id_tech` int {$sign} NOT NULL DEFAULT '0',
              `plugin_simcard_phoneoperators_id` int {$sign} NOT NULL DEFAULT '0',
              `manufacturers_id` int {$sign} NOT NULL DEFAULT '0',
              `plugin_simcard_simcardsizes_id` int {$sign} NOT NULL DEFAULT '0',
              `plugin_simcard_simcardvoltages_id` int {$sign} NOT NULL DEFAULT '0',
              `plugin_simcard_simcardtypes_id` int {$sign} NOT NULL DEFAULT '0',
              `comment` text NULL,
              `date_mod` timestamp DEFAULT NULL,
              `is_template` tinyint(1) NOT NULL DEFAULT '0',
              `is_global` tinyint(1) NOT NULL DEFAULT '0',
              `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
              `template_name` varchar(255) NULL,
              `ticket_tco` decimal(20,4) DEFAULT '0.0000',
              `is_helpdesk_visible` tinyint(1) NOT NULL DEFAULT '1',
              PRIMARY KEY (`id`),
              KEY `name` (`name`),
              KEY `entities_id` (`entities_id`),
              KEY `states_id` (`states_id`),
              KEY `plugin_simcard_phoneoperators_id` (`plugin_simcard_phoneoperators_id`),
              KEY `plugin_simcard_simcardsizes_id` (`plugin_simcard_simcardsizes_id`),
              KEY `plugin_simcard_simcardvoltages_id` (`plugin_simcard_simcardvoltages_id`),
              KEY `manufacturers_id` (`manufacturers_id`),
              KEY `serial` (`serial`),
              KEY `users_id` (`users_id`),
              KEY `users_id_tech` (`users_id_tech`),
              KEY `groups_id` (`groups_id`),
              KEY `is_template` (`is_template`),
              KEY `is_deleted` (`is_deleted`),
              KEY `is_helpdesk_visible` (`is_helpdesk_visible`),
              KEY `is_global` (`is_global`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
        }

        foreach (self::DROPDOWN_TABLES as $table) {
            if (!$DB->tableExists($table)) {
                $DB->doQuery("CREATE TABLE `$table` (
                  `id` int {$sign} NOT NULL AUTO_INCREMENT,
                  `name` varchar(255) NOT NULL,
                  `comment` text NULL,
                  PRIMARY KEY (`id`),
                  KEY `name` (`name`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
            }
        }

        if (!$DB->tableExists(self::TABLE_ITEMS)) {
            $DB->doQuery("CREATE TABLE `" . self::TABLE_ITEMS . "` (
              `id` int {$sign} NOT NULL AUTO_INCREMENT,
              `items_id` int {$sign} NOT NULL DEFAULT '0',
              `plugin_simcard_simcards_id` int {$sign} NOT NULL DEFAULT '0',
              `itemtype` varchar(100) NOT NULL,
              PRIMARY KEY (`id`),
              KEY `plugin_simcard_simcards_id` (`plugin_simcard_simcards_id`),
              KEY `item` (`itemtype`,`items_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC");
        }

        $defaults = [
            'glpi_plugin_simcard_simcardsizes'    => ['Full-SIM', 'Micro-SIM', 'Mini-SIM', 'Nano-SIM'],
            'glpi_plugin_simcard_simcardvoltages' => ['3V', '5V'],
        ];
        foreach ($defaults as $table => $names) {
            if (countElementsInTable($table) === 0) {
                foreach ($names as $name) {
                    $DB->insert($table, ['name' => $name, 'comment' => '']);
                }
            }
        }
    }

    /**
     * Upgrade from an installed older version.
     *
     * @param string $from Previously installed version
     */
    public static function upgrade(Migration $migration, string $from): void
    {
        global $DB;

        // 0. A partially failed fresh install is routed here too: complete it
        self::createSchema();

        // 1. Class names were namespaced in 2.0.0: rewrite stored itemtypes
        foreach (PLUGIN_SIMCARD_LEGACY_CLASSES as $old => $new) {
            if ($new === SimcardInjection::class) {
                // Loading this class requires the (possibly absent) Data Injection plugin,
                // which Migration::renameItemtype() would trigger: rewrite its own tables only.
                self::renameDatainjectionItemtype($old, $new);
                continue;
            }
            $migration->renameItemtype($old, $new, false);
        }

        // 2. Same for the helpdesk item types of the profiles (JSON column)
        foreach ($DB->request(['FROM' => \Profile::getTable()]) as $row) {
            $types = json_decode((string) ($row['helpdesk_item_type'] ?? ''), true);
            if (!is_array($types) || !in_array('PluginSimcardSimcard', $types, true)) {
                continue;
            }
            $types = array_map(
                static fn($type) => $type === 'PluginSimcardSimcard' ? Simcard::class : $type,
                $types
            );
            $DB->update(
                \Profile::getTable(),
                ['helpdesk_item_type' => json_encode(array_values(array_unique($types)))],
                ['id' => $row['id']]
            );
        }

        // 3. Obsolete tables
        foreach (['glpi_plugin_simcard_configs', 'glpi_plugin_simcard_profiles'] as $table) {
            if ($DB->tableExists($table)) {
                $migration->dropTable($table);
            }
        }

        // 4. Dropdown comments must be nullable (CommonDropdown::import() does not set them)
        foreach (self::DROPDOWN_TABLES as $table) {
            $migration->changeField($table, 'comment', 'comment', 'text', [
                'null'      => true,
                'nodefault' => true,
            ]);
        }

        // 5. PIN and PUK codes were stored in plain text before 2.0.0.
        // Gated on a persistent marker rather than on $from, so that they are never encrypted twice.
        if (!self::areSecretsEncrypted()) {
            $DB->beginTransaction();
            try {
                self::encryptSecrets($migration);
                self::markSecretsEncrypted();
                $DB->commit();
            } catch (Throwable $e) {
                $DB->rollBack();
                throw $e;
            }
        }
    }

    /**
     * Encrypt the plain text PIN and PUK codes with the GLPI security key.
     *
     * Must only run once, see self::areSecretsEncrypted().
     */
    private static function encryptSecrets(Migration $migration): void
    {
        global $DB;

        $glpikey = new GLPIKey();
        // hasReadErrors() first: get() emits a PHP warning when the file is missing
        if ($glpikey->hasReadErrors() || $glpikey->get() === null) {
            throw new RuntimeException(
                'Unable to read the GLPI security key (glpicrypt.key) in the GLPI configuration directory: '
                . 'it is required to encrypt the PIN and PUK codes of the SIM cards. '
                . 'Restore the key file (or create one with "php bin/console security:change_key") and run the upgrade again.'
            );
        }

        $not_empty = [];
        foreach (Simcard::SECRET_FIELDS as $field) {
            $not_empty[] = [$field => ['<>', '']];
        }
        $iterator = $DB->request([
            'SELECT' => array_merge(['id'], Simcard::SECRET_FIELDS),
            'FROM'   => self::TABLE_SIMCARDS,
            'WHERE'  => ['OR' => $not_empty],
        ]);
        foreach ($iterator as $row) {
            $values = [];
            foreach (Simcard::SECRET_FIELDS as $field) {
                $value = (string) $row[$field];
                if ($value === '') {
                    continue;
                }
                $encrypted = $glpikey->encrypt($value);
                if ($encrypted === '') {
                    throw new RuntimeException(sprintf(
                        'Unable to encrypt the PIN and PUK codes of SIM card %d with the GLPI security key (glpicrypt.key).',
                        $row['id']
                    ));
                }
                $values[$field] = $encrypted;
            }
            // Direct update: no history entry
            $DB->update(self::TABLE_SIMCARDS, $values, ['id' => $row['id']]);
        }

        // Installs upgraded from 1.x have indexes on the codes (no-op when absent)
        foreach (Simcard::SECRET_FIELDS as $field) {
            $migration->dropKey(self::TABLE_SIMCARDS, $field);
        }

        // Search options 413-416 (PIN and PUK codes) were removed
        $migration->updateDisplayPrefs([], [Simcard::class => [413, 414, 415, 416]]);
    }

    private static function areSecretsEncrypted(): bool
    {
        $values = Config::getConfigurationValues(self::CONFIG_CONTEXT, [self::CONFIG_SECRETS_ENCRYPTED]);

        return !empty($values[self::CONFIG_SECRETS_ENCRYPTED]);
    }

    private static function markSecretsEncrypted(): void
    {
        Config::setConfigurationValues(self::CONFIG_CONTEXT, [self::CONFIG_SECRETS_ENCRYPTED => 1]);
    }

    /**
     * Rewrite the itemtype columns of the Data Injection plugin tables, if present.
     */
    private static function renameDatainjectionItemtype(string $old, string $new): void
    {
        global $DB;

        foreach ($DB->listTables('glpi\_plugin\_datainjection\_%') as $table) {
            if ($DB->fieldExists($table['TABLE_NAME'], 'itemtype')) {
                $DB->update($table['TABLE_NAME'], ['itemtype' => $new], ['itemtype' => $old]);
            }
        }
    }

    public static function uninstall(Migration $migration): void
    {
        global $DB;

        $itemtypes = array_merge(
            array_values(PLUGIN_SIMCARD_LEGACY_CLASSES),
            array_keys(PLUGIN_SIMCARD_LEGACY_CLASSES)
        );

        foreach ($itemtypes as $itemtype) {
            FieldUnicity::deleteForItemtype($itemtype);
        }

        $cleaned = [
            DisplayPreference::class,
            SavedSearch::class,
            DropdownTranslation::class,
            Item_Ticket::class,
            Item_Problem::class,
            Document_Item::class,
            Infocom::class,
            Contract_Item::class,
            Log::class,
            Notepad::class,
            ReservationItem::class,
            NetworkPort::class,
            Fieldblacklist::class,
        ];
        foreach ($cleaned as $class) {
            $item = new $class();
            foreach ($itemtypes as $itemtype) {
                $item->deleteByCriteria(['itemtype' => $itemtype], true, false);
            }
        }

        Config::deleteConfigurationValues(self::CONFIG_CONTEXT, [self::CONFIG_SECRETS_ENCRYPTED]);

        \ProfileRight::deleteProfileRights([Profile::RIGHT_SIMCARD_SIMCARD]);
        unset($_SESSION['glpiactiveprofile'][Profile::RIGHT_SIMCARD_SIMCARD]);

        // Remove SIM cards from the helpdesk item types of all profiles
        foreach ($DB->request(['FROM' => \Profile::getTable()]) as $row) {
            $types = json_decode((string) ($row['helpdesk_item_type'] ?? ''), true);
            if (!is_array($types)) {
                continue;
            }
            $kept = array_values(array_diff($types, $itemtypes));
            if (count($kept) !== count($types)) {
                $DB->update(
                    \Profile::getTable(),
                    ['helpdesk_item_type' => json_encode($kept)],
                    ['id' => $row['id']]
                );
            }
        }
        if (isset($_SESSION['glpiactiveprofile']['helpdesk_item_type'])) {
            $_SESSION['glpiactiveprofile']['helpdesk_item_type'] = array_values(
                array_diff($_SESSION['glpiactiveprofile']['helpdesk_item_type'], $itemtypes)
            );
        }

        if (Plugin::isPluginActive('datainjection') && class_exists('PluginDatainjectionModel')) {
            foreach ($itemtypes as $itemtype) {
                \PluginDatainjectionModel::clean(['itemtype' => $itemtype]);
            }
        }

        $tables = array_merge(
            [self::TABLE_ITEMS, self::TABLE_SIMCARDS],
            self::DROPDOWN_TABLES,
            ['glpi_plugin_simcard_configs', 'glpi_plugin_simcard_profiles']
        );
        foreach ($tables as $table) {
            if ($DB->tableExists($table)) {
                $migration->dropTable($table);
            }
        }
    }
}

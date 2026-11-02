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

use CommonDropdown;
use DBConnection;
use DisplayPreference;
use DropdownTranslation;
use Migration;
use SavedSearch;

/// Class SimcardVoltage
class SimcardVoltage extends CommonDropdown
{
    public static function getTypeName($nb = 0)
    {
        return __('Voltage', 'simcard');
    }

    public static function install(Migration $migration): void
    {
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE IF NOT EXISTS `$table` (
              `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL,
              `comment` text NOT NULL,
              PRIMARY KEY (`id`),
              KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
            foreach (['3V', '5V'] as $name) {
                $DB->insert($table, ['name' => $name, 'comment' => '']);
            }
        }
    }

    public static function upgrade(Migration $migration): void
    {
        global $DB;
        $table = self::getTable();
        $DB->doQuery("ALTER TABLE `$table` ENGINE=InnoDB");
    }

    public static function uninstall(): void
    {
        global $DB;

        foreach ([DisplayPreference::class, SavedSearch::class] as $itemtype) {
            $item = new $itemtype();
            $item->deleteByCriteria(['itemtype' => self::class]);
        }

        // Remove dropdowns localization
        $dropdownTranslation = new DropdownTranslation();
        $dropdownTranslation->deleteByCriteria(['itemtype' => self::class], true, false);

        $table = self::getTable();
        $DB->doQuery("DROP TABLE IF EXISTS `$table`");
    }

    /**
     * Find or create the equivalent of dropdown value $ID in entity $entity.
     *
     * @return int ID of the value in the target entity, 0 if the source value does not exist
     */
    public static function transfer(int $ID, int $entity): int
    {
        if ($ID <= 0) {
            return 0;
        }

        $source = new self();
        if (!$source->getFromDB($ID)) {
            return 0;
        }

        $target = new self();
        return (int) $target->import([
            'name'        => $source->fields['name'],
            'comment'     => $source->fields['comment'] ?? '',
            'entities_id' => $entity,
        ]);
    }
}

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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * 
 * @author dethegeek
 * @since 1.3
 *
 */
class PluginSimcardConfig extends CommonDBTM
{

   // Type reservation : https://forge.indepnet.net/projects/plugins/wiki/PluginTypesReservation
   // Reserved range   : [10126, 10135]
   const RESERVED_TYPE_RANGE_MIN = 10126;
   const RESERVED_TYPE_RANGE_MAX = 10135;

   public static array $config = array();

   public static function install(Migration $migration): void
   {
       global $DB;

       $default_charset = DBConnection::getDefaultCharset();
       $default_collation = DBConnection::getDefaultCollation();
       $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

      $table = getTableForItemType(__CLASS__);
      if (!$DB->tableExists($table)) {
         $query = "CREATE TABLE `" . $table . "` (
                `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `type` varchar(255)  DEFAULT NULL,
                `value` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unicity` (`type`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
         $DB->doQuery($query) or die($DB->error());
         $query = "INSERT INTO `" . $table . "` 
                (`type`,`value`)
               VALUES ('Version', '" . PLUGIN_SIMCARD_VERSION . "')";
         $DB->doQuery($query) or die($DB->error());
      }
   }

   public static function upgrade(Migration $migration): void
   {
      global $DB;

      switch (plugin_simcard_currentVersion()) {
         default:
            $table = getTableForItemType(__CLASS__);
            $query = "UPDATE `" . $table . "`
                      SET `value`= '" . PLUGIN_SIMCARD_VERSION . "'
                      WHERE `type`='Version'";
            $DB->doQuery($query) or die($DB->error());
      }
   }

   public static function uninstall(): void
   {
      global $DB;

      $displayPreference = new DisplayPreference();
      $displayPreference->deleteByCriteria(array("`num` >= " . self::RESERVED_TYPE_RANGE_MIN . " AND `num` <= " . self::RESERVED_TYPE_RANGE_MAX));

      $table = getTableForItemType(__CLASS__);
      $query = "DROP TABLE IF EXISTS `" . $table . "`";

      $DB->doQuery($query) or die($DB->error());
   }

   public static function loadCache(): void
   {
      global $DB;

      $table = getTableForItemType(__CLASS__);
      self::$config = array();
      $query = "SELECT * FROM `" . $table . "`";
      $result = $DB->doQuery($query);
      while ($data = $DB->fetch_array($result)) {
         self::$config[$data['type']] = $data['value'];
      }
   }

   /**
    * Add configuration value, if not already present
    *
    * @param $name field name
    * @param $value field value
    *
    * @return integer the new id of the added item (or FALSE if fail)
    **/
   public function addValue($name, $value): false|int
   {
      $existing_value = $this->getValue($name);
      if (!is_null($existing_value)) {
         return false;
      } else {
         return $this->add(array(
            'type'       => $name,
            'value'      => $value
         ));
      }
   }

   /**
    * Get configuration value
    *
    * @param $name field name
    *
    * @return field value for an existing field, FALSE otherwise
    **/
   public function getValue($name): ?field
   {
      if (isset(self::$config[$name])) {
         return self::$config[$name];
      }

      $config = current($this->find(["type = '$name'"]));
      if (isset($config['value'])) {
         return $config['value'];
      }
      return NULL;
   }

   /**
    * Update configuration value
    *
    * @param $name field name
    * @param $value field value
    *
    * @return boolean : TRUE on success
    **/
   public function updateValue($name, $value): bool
   {
      $config = current($this->find(["type = '$name'"]));
      if (isset($config['id'])) {
         return $this->update(array('id' => $config['id'], 'value' => $value));
      } else {
         return $this->add(array('type' => $name, 'value' => $value));
      }
   }
}

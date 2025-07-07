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

/// Class SimcardSize
class PluginSimcardSimcardSize extends CommonDropdown
{


   public static function getTypeName($nb = 0)
   {
      global $LANG;
      return __s('Size', 'simcard');
   }

   public static function install(Migration $migration)
   {
      global $DB;

       $default_charset = DBConnection::getDefaultCharset();
       $default_collation = DBConnection::getDefaultCollation();
       $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

      $table = getTableForItemType(__CLASS__);
      if (!$DB->TableExists($table)) {
         $query = "CREATE TABLE IF NOT EXISTS `$table` (
           `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
           `name` varchar(255) NOT NULL,
           `comment` text NOT NULL,
           PRIMARY KEY (`id`),
           KEY `name` (`name`)
         ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
         $DB->doQuery($query) or die("Error adding table $table");

         $query = "INSERT INTO `$table` (`id`, `name`, `comment`) VALUES
                     (1, 'Full-SIM', ''),
                     (2, 'Micro-SIM', ''),
                     (3, 'Mini-SIM', ''),
                     (4, 'Nano-SIM', '');";
         $DB->doQuery($query) or die("Error adding simcard sizes");
      }
   }

   /**
    * 
    *
    * @since 1.3
    **/
   public static function upgrade(Migration $migration)
   {
      global $DB;
      $table = getTableForItemType(__CLASS__);
      $DB->doQuery("ALTER TABLE `$table` ENGINE=InnoDB");
   }

   public static function uninstall()
   {
      global $DB;

      foreach (array('DisplayPreference', 'SavedSearch') as $itemtype) {
         $item = new $itemtype();
         $item->deleteByCriteria(array('itemtype' => __CLASS__));
      }

      // Remove dropdowns localization
      $dropdownTranslation = new DropdownTranslation();
      $dropdownTranslation->deleteByCriteria(array("itemtype = 'PluginSimcardSimcardSize'"), 1);

      $table = getTableForItemType(__CLASS__);
      $DB->doQuery("DROP TABLE IF EXISTS `$table`");
   }

   public static function transfer($ID, $entity)
   {
      global $DB;

      $simcardSize = new self();

      if ($ID > 0) {
         // Not already transfer
         // Search init item
         $query = "SELECT *
                   FROM `" . $simcardSize::getTable() . "`
                   WHERE `id` = '$ID'";

         if ($result = $DB->doQuery($query)) {
            if ($DB->numrows($result)) {
               $data                 = $DB->fetchAssoc($result);
               $data                 = Toolbox::addslashes_deep($data);
               $input['name']        = $data['name'];
               $input['entities_id'] = $entity;
               $newID                = $simcardSize->getID($input);

               if ($newID < 0) {
                  $newID = $simcardSize->import($input);
               }

               return $newID;
            }
         }
      }
      return 0;
   }
}

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

/// Class Simcard
class PluginSimcardSimcard extends CommonDBTM {

   // From CommonDBTM
   //static $types = array('');
  public $dohistory = true;
  
  static $rightname = PluginSimcardProfile::RIGHT_SIMCARD_SIMCARD;
  protected $usenotepad            = true;
  
  //~ static $types = array('Computer', 'Monitor', 'NetworkEquipment', 'Peripheral', 'Phone', 'Printer', 'Software', 'Entity');
  static $types = array('Phone' , 'Entity');
  
   /**
    * Name of the type
    *
    * @param $nb  integer  number of item in the type (default 0)
   **/
   static function getTypeName($nb=0) {
      global $LANG;
      return _n('SIM card', 'SIM cards', $nb, 'simcard');
   }

   /**
    * @since version 0.85
    *
    * @see commonDBTM::getRights()
    **/
   function getRights($interface='central') {
      $rights = parent::getRights();
      $rights[PluginSimcardProfile::SIMCARD_ASSOCIATE_TICKET] = __('Associable to a ticket');
     
     return $rights;
   }

   function defineTabs($options=array()) {
      global $LANG;
      $ong     = array();
      $this->addDefaultFormTab($ong);
      if ($this->fields['id'] > 0) {
         if (!isset($options['withtemplate']) || empty($options['withtemplate'])) {
            $this->addStandardTab('PluginSimcardSimcard_Item', $ong, $options);
            $this->addStandardTab('NetworkPort', $ong, $options);
            $this->addStandardTab('Document_Item',$ong,$options);
            $this->addStandardTab('Infocom',$ong,$options);
            $this->addStandardTab('Contract_Item', $ong, $options);
            if ($this->fields['is_helpdesk_visible'] == 1) {
               $this->addStandardTab('Ticket',$ong,$options);
               $this->addStandardTab('Item_Problem', $ong, $options);
            }
            $this->addStandardTab('Notepad',$ong,$options);
            $this->addStandardTab('Log',$ong,$options);
            $this->addStandardTab('Event',$ong,$options);
         } else {
            $this->addStandardTab('Infocom',$ong,$options);
            $this->addStandardTab('Contract_Item', $ong, $options);
            $this->addStandardTab('Document_Item',$ong,$options);
            $this->addStandardTab('Log',$ong,$options);
            $this->addStandardTab('Event',$ong,$options);
         }
      } else {
         $ong[1] = __s('Main');
      }

      return $ong;
   }

   /**
    * Print the simcard form
    *
    * @param $ID        integer ID of the item
    * @param $options   array
    *     - target for the Form
    *     - withtemplate template or basic simcard
    *
    *@return Nothing (display)
   **/
    function showForm($ID, $options=array()) {
      global $CFG_GLPI, $DB, $LANG;

      if (!$this->canView()) return false;
      
      $target       = $this->getFormURL();
      $withtemplate = '';

      if (isset($options['target'])) {
        $target = $options['target'];
      }

      if (isset($options['withtemplate'])) {
         $withtemplate = $options['withtemplate'];
      }

      $this->showFormHeader($options);

      if (isset($options['itemtype']) && isset($options['items_id'])) {
         echo "<tr class='tab_bg_1'>";
         echo "<td>".__s('Associated element')."</td>";
         echo "<td>";
         $item = new $options['itemtype'];
         $item->getFromDB($options['items_id']);
         echo $item->getLink(1);
         echo "</td>";
         echo "<td colspan='2'></td></tr>\n";
         echo "<input type='hidden' name='_itemtype' value='".$options['itemtype']."'>";
         echo "<input type='hidden' name='_items_id' value='".$options['items_id']."'>";
      }
      
      
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__s('Name')."</td>";
      echo "<td>";
      $objectName = autoName($this->fields["name"], "name",
                             (isset($options['withtemplate']) && $options['withtemplate']==2),
                             $this->getType(), $this->fields["entities_id"]);
      Html::autocompletionTextField($this, 'name', array('value' => $objectName));
      echo "</td>";
      echo "<td>".__s('Status')."</td>";
      echo "<td>";
      Dropdown::show('State', array('value' => $this->fields["states_id"]));
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__s('Location')."</td>";
      echo "<td>";
      Dropdown::show('Location', array('value'  => $this->fields["locations_id"],
                                       'entity' => $this->fields["entities_id"]));
      echo "</td>";
      echo "<td>".__s('Type of SIM card', 'simcard')."</td>";
      echo "<td>";
      Dropdown::show('PluginSimcardSimcardType',
                     array('value' => $this->fields["plugin_simcard_simcardtypes_id"]));
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__s('Technician in charge of the hardware')."</td>";
      echo "<td>";
      User::dropdown(array('name'   => 'users_id_tech',
                           'value'  => $this->fields["users_id_tech"],
                           'right'  => 'interface',
                           'entity' => $this->fields["entities_id"]));
      echo "</td>";
      echo "<td>".__s('Size', 'simcard')."</td>";
      echo "<td>";
      Dropdown::show('PluginSimcardSimcardSize',
                     array('value' => $this->fields["plugin_simcard_simcardsizes_id"]));
      echo "</td></tr>\n";

//       TODO : Add group in charge of hardware      
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__s('Group in charge of the hardware')."</td>";
      echo "<td>";
      Group::dropdown(array('name'      => 'groups_id_tech',
      'value'     => $this->fields['groups_id_tech'],
      'entity'    => $this->fields['entities_id'],
      'condition' => '`is_assign`'));
      echo "</td>";
      
      echo "<td>".__s('Voltage', 'simcard')."</td>";
      echo "<td>";
      Dropdown::show('PluginSimcardSimcardVoltage',
                     array('value' => $this->fields["plugin_simcard_simcardvoltages_id"]));
      echo "</td></tr>\n";
      
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__s('Provider', 'simcard')."</td>";
      echo "<td>";
      Dropdown::show('PluginSimcardPhoneOperator',
                     array('value' => $this->fields["plugin_simcard_phoneoperators_id"]));
      echo "</td>";

      echo "<td>" . __s('Associable items to a ticket') . "&nbsp;:</td><td>";
      Dropdown::showYesNo('is_helpdesk_visible',$this->fields['is_helpdesk_visible']);
      echo "</td></tr>\n";
   
      
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__s('User')."</td>";
      echo "<td>";
      User::dropdown(array('value'  => $this->fields["users_id"],
                           'entity' => $this->fields["entities_id"],
                           'right'  => 'all'));
      echo "</td>";

      echo "<input type='hidden' name='is_global' value='1'>";

      echo "<td>".__s("Inventory number")."</td>";
      echo "<td>";
      $objectName = autoName($this->fields["otherserial"], "otherserial",
                             (isset($options['withtemplate']) && $options['withtemplate']==2),
                             $this->getType(), $this->fields["entities_id"]);
      Html::autocompletionTextField($this, 'otherserial', array('value' => $objectName));
      echo "</td></tr>\n";
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__s('Group')."</td>";
      echo "<td>";
      Dropdown::show('Group', array('value'     => $this->fields["groups_id"],
                                    'entity'    => $this->fields["entities_id"]));

      echo "</td></tr>\n";
      
      echo "<tr class='tab_bg_1'>";
      echo "<td>".__s('Phone number', 'simcard')."</td>";
      echo "<td>";
      Html::autocompletionTextField($this,'phonenumber');
      echo "</td>";
      echo "<td rowspan='6'>".__s('Comments')."</td>";
      echo "<td rowspan='6' class='middle'>";
      echo "<textarea cols='45' rows='15' name='comment' >".$this->fields["comment"]."</textarea>";
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".__s('IMSI', 'simcard')."</td>";
      echo "<td>";
      Html::autocompletionTextField($this,'serial');
      echo "</td></tr>\n";
      
      //Only show PIN and PUK code to users who can write (theses informations are highly sensible)
      if (PluginSimcardSimcard::canUpdate()) {
         echo "<tr class='tab_bg_1'>";
         echo "<td>".__s('Pin 1', 'simcard')."</td>";
         echo "<td>";
         Html::autocompletionTextField($this,'pin');
         echo "</td></tr>\n";
         
         echo "<tr class='tab_bg_1'>";
         echo "<td>".__s('Pin 2', 'simcard')."</td>";
         echo "<td>";
         Html::autocompletionTextField($this,'pin2');
         echo "</td></tr>\n";
         
         echo "<tr class='tab_bg_1'>";
         echo "<td>".__s('Puk 1', 'simcard')."</td>";
         echo "<td>";
         Html::autocompletionTextField($this,'puk');
         echo "</td></tr>\n";

         echo "<tr class='tab_bg_1'>";
         echo "<td>".__s('Puk 2', 'simcard')."</td>";
         echo "<td>";
         Html::autocompletionTextField($this,'puk2');
         echo "</td></tr>\n";
      }

      $this->showFormButtons($options);
      //$this->addDivForTabs();

      return true;
   }

   function prepareInputForAdd($input) {

      if (isset($input["id"]) && $input["id"]>0) {
         $input["_oldID"] = $input["id"];
      }
      unset($input['id']);
      unset($input['withtemplate']);

      return $input;
   }
   
   function post_addItem() {
      global $DB, $CFG_GLPI;

      // Manage add from template
      if (isset($this->input["_oldID"])) {
         Infocom::cloneItem($this->getType(), $this->input["_oldID"], $this->fields['id']);
         Contract_Item::cloneItem($this->getType(), $this->input["_oldID"], $this->fields['id']);
         Document_Item::cloneItem($this->getType(), $this->input["_oldID"], $this->fields['id']);          
      }
   
      if (isset($this->input['_itemtype']) && isset($this->input['_items_id'])) {
         $simcard_item = new PluginSimcardSimcard_Item();
         $tmp['plugin_simcard_simcards_id'] = $this->getID();
         $tmp['itemtype'] = $this->input['_itemtype'];
         $tmp['items_id'] = $this->input['_items_id'];
         $simcard_item->add($tmp);
      }
      
   }
   
    function rawSearchOptions() {
        global $CFG_GLPI, $LANG;

        $tab = [];

        $tab[] = [
            'id'                 => 'common',
            'name'               => __s('SIM card', 'simcard')
        ];

        $tab[] = [
            'id'              => '1',
            'table'           => $this->getTable(),
            'field'           => 'name',
            'name'            => __('Name'),
            'datatype'        => 'itemlink',
            'massiveaction'   => false
        ];

        $tab[] = [
            'id'              => '2',
            'table'           => $this->getTable(),
            'field'           => 'id',
            'name'            => __('ID'),
            'datatype'        => 'number',
            'massiveaction'   => false
        ];

        $tab[] = [
            'id'              => '4',
            'table'           => 'glpi_plugin_simcard_simcardtypes',
            'field'           => 'name',
            'name'            => __('Type'),
            'datatype'        => 'dropdown',
            'massiveaction'   => false
        ];

        $tab[] = [
            'id'              => '5',
            'table'           => $this->getTable(),
            'field'           => 'serial',
            'name'            => __('IMSI', 'simcard'),
            'datatype'        => 'text',
            'massiveaction'   => false
        ];

        $tab[] = [
            'id'              => '6',
            'table'           => $this->getTable(),
            'field'           => 'otherserial',
            'name'            => __('Inventory number'),
            'datatype'        => 'text',
            'massiveaction'   => false
        ];

        $tab[] = [
            'id'              => '16',
            'table'           => $this->getTable(),
            'field'           => 'comment',
            'name'            => __('Comments'),
            'datatype'        => 'text'
        ];

        /*
        $tab[3]['checktype']       = 'text';
        $tab[3]['displaytype']     = 'dropdown';
        $tab[3]['injectable']      = true;

        $tab[91]['injectable']     = false;
        $tab[93]['injectable']     = false;
        */

        $tab[] = [
            'id'              => '19',
            'table'           => $this->getTable(),
            'field'           => 'date_mod',
            'name'            => __('Last update'),
            'datatype'        => 'datetime',
            'massiveaction'   => false
        ];

        $tab[] = [
            'id'              => '23',
            'table'           => 'glpi_plugin_simcard_simcardvoltages',
            'field'           => 'name',
            'name'            => __('Voltage', 'simcard'),
            'datatype'        => 'dropdown',
            'massiveaction'   => false
        ];

        $tab[] = [
            'id'                 => '24',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'linkfield'          => 'users_id_tech',
            'name'               => __('Technician in charge of the hardware'),
            'datatype'           => 'dropdown',
            'right'              => 'own_ticket'
        ];

        $tab[] = [
            'id'              => '25',
            'table'           => 'glpi_plugin_simcard_simcardsizes',
            'field'           => 'name',
            'name'            => __('Size', 'simcard'),
            'datatype'        => 'dropdown',
            'massiveaction'   => false
        ];

        $tab[] = [
            'id'              => '26',
            'table'           => 'glpi_plugin_simcard_phoneoperators',
            'field'           => 'name',
            'name'            => __('Provider', 'simcard'),
            'datatype'        => 'dropdown',
            'massiveaction'   => false
        ];

        $tab[] = [
            'id'              => '27',
            'table'           => $this->getTable(),
            'field'           => 'phonenumber',
            'name'            => __('Phone number', 'simcard'),
            'datatype'        => 'string',
            'massiveaction'   => false
        ];

        if (PluginSimcardSimcard::canUpdate()) {
            $tab[] = [
                'id'              => '28',
                'table'           => $this->getTable(),
                'field'           => 'pin',
                'name'            => __('Pin 1', 'simcard'),
                'datatype'        => 'string',
                'massiveaction'   => false
            ];

            $tab[] = [
                'id'              => '29',
                'table'           => $this->getTable(),
                'field'           => 'puk',
                'name'            => __('Puk 1', 'simcard'),
                'datatype'        => 'string',
                'massiveaction'   => false
            ];

            $tab[] = [
                'id'              => '30',
                'table'           => $this->getTable(),
                'field'           => 'pin2',
                'name'            => __('Pin 2', 'simcard'),
                'datatype'        => 'string',
                'massiveaction'   => false
            ];

            $tab[] = [
                'id'              => '32',
                'table'           => $this->getTable(),
                'field'           => 'puk2',
                'name'            => __('Puk 2', 'simcard'),
                'datatype'        => 'string',
                'massiveaction'   => false
            ];
        }

        $tab[] = [
            'id'                 => '31',
            'table'              => 'glpi_states',
            'field'              => 'completename',
            'name'               => __('Status'),
            'datatype'           => 'dropdown'
        ];

        $tab[] = [
            'id'                 => '71',
            'table'              => 'glpi_groups',
            'field'              => 'completename',
            'name'               => __('Group'),
            'datatype'           => 'dropdown'
        ];

        $tab[] = [
            'id'                 => '49',
            'table'              => 'glpi_groups',
            'field'              => 'completename',
            'linkfield'          => 'groups_id_tech',
            'name'               => __('Group in charge of the hardware'),
            'condition'          => ['is_assign' => 1],
            'datatype'           => 'dropdown'
        ];

        $tab[] = [
            'id'                 => '70',
            'table'              => 'glpi_users',
            'field'              => 'name',
            'name'               => __('User'),
            'datatype'           => 'dropdown',
            'right'              => 'all'
        ];

        $tab[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => __('Entity'),
            'massiveaction'      => false,
            'datatype'           => 'dropdown'
        ];

        $tab[] = [
            'id'              => '90',
            'table'           => $this->getTable(),
            'field'           => 'notepad',
            'name'            => __('Notes'),
            'linkfield'       => 'notepad',
            'datatype'        => 'text',
            'massiveaction'   => false
        ];

        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());
        $tab = array_merge($tab, Notepad::rawSearchOptionsToAdd());

        return $tab;
    }
   
   /**
    * Installation of the itemtype
    * 
    * @param Migration $migration migration helper instance
    */
   static function install(Migration $migration) {
      global $DB;
      $table = getTableForItemType(__CLASS__);
      if (!$DB->TableExists($table)) {
         $query = "CREATE TABLE IF NOT EXISTS `$table` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `entities_id` int(11) NOT NULL DEFAULT '0',
              `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
              `phonenumber` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
              `serial` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
              `pin` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
              `pin2` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
              `puk` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
              `puk2` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
              `otherserial` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
              `states_id` int(11) NOT NULL DEFAULT '0',
              `locations_id` int(11) NOT NULL DEFAULT '0',
              `users_id` int(11) NOT NULL DEFAULT '0',
              `users_id_tech` int(11) NOT NULL DEFAULT '0',
              `groups_id` int(11) NOT NULL DEFAULT '0',
              `groups_id_tech` int(11) NOT NULL DEFAULT '0',
              `plugin_simcard_phoneoperators_id` int(11) NOT NULL DEFAULT '0',
              `manufacturers_id` int(11) NOT NULL DEFAULT '0',
              `plugin_simcard_simcardsizes_id` int(11) NOT NULL DEFAULT '0',
              `plugin_simcard_simcardvoltages_id` int(11) NOT NULL DEFAULT '0',
              `plugin_simcard_simcardtypes_id` int(11) NOT NULL DEFAULT '0',
              `comment` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
              `date_mod` datetime DEFAULT NULL,
              `is_template` tinyint(1) NOT NULL DEFAULT '0',
              `is_global` tinyint(1) NOT NULL DEFAULT '0',
              `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
              `template_name` varchar(255) COLLATE utf8_unicode_ci NULL,
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
              KEY `pin` (`pin`),
              KEY `pin2` (`pin2`),
              KEY `puk` (`puk`),
              KEY `puk2` (`puk2`),
              KEY `serial` (`serial`),
              KEY `users_id` (`users_id`),
              KEY `users_id_tech` (`users_id_tech`),
              KEY `groups_id` (`groups_id`),
              KEY `is_template` (`is_template`),
              KEY `is_deleted` (`is_deleted`),
              KEY `is_helpdesk_visible` (`is_helpdesk_visible`),
              KEY `is_global` (`is_global`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ;";
         $DB->query($query) or die("Error adding table $table");
      }
      $query = "INSERT INTO `glpi_displaypreferences` (`itemtype`, `num`, `rank`, `users_id`) VALUES ('PluginSimcardSimcard', 27, 1, 0);";
      $DB->queryOrDie($query, "Error while setting global displaypreferences");
      $query = "INSERT INTO `glpi_displaypreferences` (`itemtype`, `num`, `rank`, `users_id`) VALUES ('PluginSimcardSimcard', 26, 2, 0);";
      $DB->queryOrDie($query, "Error while setting global displaypreferences");
      $query = "INSERT INTO `glpi_displaypreferences` (`itemtype`, `num`, `rank`, `users_id`) VALUES ('PluginSimcardSimcard', 25, 3, 0);";
      $DB->queryOrDie($query, "Error while setting global displaypreferences");
   }
   
   static function upgrade(Migration $migration) {
       global $DB;
       $table = getTableForItemType(__CLASS__);
       $DB->query("ALTER TABLE `$table` ENGINE=InnoDB");
   }
   
   static function uninstall() {
      global $DB;

      // Remove unicity constraints on simcards
      FieldUnicity::deleteForItemtype("SimcardSimcard");

      foreach (array('Notepad', 'DisplayPreference', 'Contract_Item', 'Infocom', 'Fieldblacklist', 'Document_Item', 'Log', 'SavedSearch') as $itemtype) {
         $item = new $itemtype();
         $item->deleteByCriteria(array('itemtype' => __CLASS__));
      }
      
      $plugin = new Plugin();
      if ($plugin->isActivated('datainjection') && class_exists('PluginDatainjectionModel')) {
         PluginDatainjectionModel::clean(array('itemtype' => __CLASS__));
      }

      if ($plugin->isInstalled('customfields') && $plugin->isActivated('customfields')) {
         PluginCustomfieldsItemtype::unregisterItemtype('PluginSimcardSimcard');
      }
      
      $table = getTableForItemType(__CLASS__);
      $DB->query("DROP TABLE IF EXISTS `$table`");
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;

      if (in_array(get_class($item), PluginSimcardSimcard_Item::getClasses())
         || get_class($item) == 'Profile') {
         return array(1 => _sn('SIM card', 'SIM cards', 2, 'simcard'));
      } elseif (get_class($item) == __CLASS__) {
         return _sn('SIM card', 'SIM cards', 2, 'simcard');
      }
      return '';
  }

   /**
    *  Show tab content for a simcard item
    * 
    * @param CommonGLPI $item
    * @param number $tabnum
    * @param number $withtemplate
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      
      $self=new self();
      if($item->getType()=='PluginSimcardSimcard') {
         $self->showtotal($item->getField('id'));
      }
      return true;
   }

  /**
    * Type than could be linked to a Rack
    *
    * @param $all boolean, all type, or only allowed ones
    *
    * @return array of types
   **/
   static function getTypes($all=false) {

      if ($all) {
         return self::$types;
      }

      // Only allowed types
      $types = self::$types;

      foreach ($types as $key => $type) {
         if (!class_exists($type)) {
            continue;
         }

         $item = new $type();
         if (!$item->canView()) {
            unset($types[$key]);
         }
      }
      return $types;
   }
   
   /**
    * Add menu entries the plugin needs to show
    * 
    * @return array
    */
   static function getMenuContent() {
   	global $CFG_GLPI;
   		
   	$menu = array();
      $menu['title'] = self::getTypeName(2);
      $menu['page']  = self::getSearchURL(false);
      $menu['links']['search'] = self::getSearchURL(false);
      if (self::canCreate()) {
         $menu['links']['add'] = '/front/setup.templates.php?itemtype=PluginSimcardSimcard&add=1';
         $menu['links']['template'] = '/front/setup.templates.php?itemtype=PluginSimcardSimcard&add=0';
      }
      return $menu;
   }
      

   /**
    * Actions done when item is deleted from the database
    *
    * @return nothing
    * */
   function cleanDBonPurge() {
      $link = new PluginSimcardSimcard_Item();
      $link->cleanDBonItemDelete($this->getType(), $this->getID());
   }

   /**
    * Delete an item in the database.
    *
    * @see CommonDBTM::delete()
    *
    * @param $input     array    the _POST vars returned by the item form when press delete
    * @param $force     boolean  force deletion (default 0)
    * @param $history   boolean  do history log ? (default 1)
    *
    * @return boolean : true on success
   **/
   function delete(array $input, $force=0, $history=1) {
      $deleteSuccessful = parent::delete($input, $force, $history);
      if ($deleteSuccessful != false) {
	      if ($force == 1) {
	      	$notepad = new Notepad();
	      	$notepad->deleteByCriteria(array(
	      	   'itemtype' => 'PluginSimcardSimcard',
	      	   'items_id' => $input['id']
	      	));
	      }
      }
      return $deleteSuccessful;
   }
   
   /**
    * @since version 0.85
    *
    * @see CommonDBTM::getSpecificMassiveActions()
    * */
   function getSpecificMassiveActions($checkitem = NULL) {
      $isadmin = static::canUpdate();
      $actions = parent::getSpecificMassiveActions($checkitem);

      if ($_SESSION['glpiactiveprofile']['interface'] == 'central') {
         if ($isadmin) {
            if (Session::haveRight('transfer', READ) && Session::isMultiEntitiesMode()) {
               $actions['PluginSimcardSimcard'.MassiveAction::CLASS_ACTION_SEPARATOR.'transfer'] = __('Transfer');
            }
         }
      }
      return $actions;
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::showMassiveActionsSubForm()
    * */
   static function showMassiveActionsSubForm(MassiveAction $ma) {

      switch ($ma->getAction()) {
         case "transfer" :
            Dropdown::show('Entity');
            echo Html::submit(_x('button', 'Post'), array('name' => 'massiveaction'));
            return true;
            break;
      }
      return parent::showMassiveActionsSubForm($ma);
   }

   /**
    * @since version 0.85
    *
    * @see CommonDBTM::processMassiveActionsForOneItemtype()
    * */
   static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids) {
      global $DB;

      switch ($ma->getAction()) {
         case "transfer" :
            $input = $ma->getInput();
            if ($item->getType() == 'PluginSimcardSimcard') {
               foreach ($ids as $key) {
                  // Types
                  $item->getFromDB($key);
                  $type = PluginSimcardSimcardType::transfer($item->fields["plugin_simcard_simcardtypes_id"], $input['entities_id']);
                  if ($type > 0) {
                     $values["id"]                              = $key;
                     $values["plugin_simcard_simcardtypes_id"] = $type;
                     $item->update($values);
                  }
                  
                  // Size
                  $size = PluginSimcardSimcardSize::transfer($item->fields["plugin_simcard_simcardsizes_id"], $input['entities_id']);
                  if ($size > 0) {
                     $values["id"]                             = $key;
                     $values["plugin_simcard_simcardsizes_id"] = $size;
                     $item->update($values);
                  }
                  
                  // Voltage
                  $voltage = PluginSimcardSimcardVoltage::transfer($item->fields["plugin_simcard_simcardvoltages_id"], $input['entities_id']);
                  if ($voltage > 0) {
                     $values["id"]                                = $key;
                     $values["plugin_simcard_simcardvoltages_id"] = $voltage;
                     $item->update($values);
                  }
                  
                  // Phoneoperator
                  $phoneoperator = PluginSimcardPhoneOperator::transfer($item->fields["plugin_simcard_phoneoperators_id"], $input['entities_id']);
                  if ($phoneoperator > 0) {
                     $values["id"]                                = $key;
                     $values["plugin_simcard_phoneoperators_id"] = $phoneoperator;
                     $item->update($values);
                  }

                  unset($values);
                  $values["id"]          = $key;
                  $values["entities_id"] = $input['entities_id'];

                  if ($item->update($values)) {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_OK);
                  } else {
                     $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_KO);
                  }
               }
            }
            return;
      }
      parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
   }

}
?>

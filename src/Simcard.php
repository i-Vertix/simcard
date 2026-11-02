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

use CommonDBTM;
use CommonGLPI;
use Contract_Item;
use DBConnection;
use DisplayPreference;
use Document_Item;
use Dropdown;
use Fieldblacklist;
use FieldUnicity;
use Group;
use Html;
use Infocom;
use Item_Problem;
use Item_Ticket;
use Location;
use Log;
use MassiveAction;
use Migration;
use Notepad;
use Plugin;
use SavedSearch;
use Session;
use User;

/// Class Simcard
class Simcard extends CommonDBTM
{
    // From CommonDBTM
    public bool $dohistory = true;

    public static string $rightname = Profile::RIGHT_SIMCARD_SIMCARD;
    protected bool $usenotepad = true;

    public static $types = ['Phone', 'Entity'];

    /**
     * Name of the type
     *
     * @param int $nb number of item in the type (default 0)
     **/
    public static function getTypeName($nb = 0)
    {
        return _n('SIM card', 'SIM cards', $nb, 'simcard');
    }

    public static function getSectorizedDetails(): array
    {
        return ['assets', self::class];
    }

    /**
     * @see CommonDBTM::getRights()
     **/
    public function getRights($interface = 'central')
    {
        $rights = parent::getRights();
        $rights[Profile::SIMCARD_ASSOCIATE_TICKET] = __('Associable to a ticket');

        return $rights;
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        if ($this->fields['id'] > 0) {
            if (empty($options['withtemplate'])) {
                $this->addStandardTab(Simcard_Item::class, $ong, $options);
                $this->addStandardTab(Document_Item::class, $ong, $options);
                $this->addStandardTab(Infocom::class, $ong, $options);
                $this->addStandardTab(Contract_Item::class, $ong, $options);
                if ($this->fields['is_helpdesk_visible'] == 1) {
                    $this->addStandardTab(Item_Ticket::class, $ong, $options);
                    $this->addStandardTab(Item_Problem::class, $ong, $options);
                }
                $this->addStandardTab(Notepad::class, $ong, $options);
                $this->addStandardTab(Log::class, $ong, $options);
            } else {
                $this->addStandardTab(Infocom::class, $ong, $options);
                $this->addStandardTab(Contract_Item::class, $ong, $options);
                $this->addStandardTab(Document_Item::class, $ong, $options);
                $this->addStandardTab(Log::class, $ong, $options);
            }
        } else {
            $ong[1] = __('Main');
        }

        return $ong;
    }

    /**
     * Print the simcard form
     *
     * @param int   $ID      ID of the item
     * @param array $options
     *     - target for the Form
     *     - withtemplate template or basic simcard
     *
     * @return bool (display)
     **/
    public function showForm($ID, array $options = [])
    {
        if (!self::canView()) {
            return false;
        }

        $this->showFormHeader($options);

        if (
            isset($options['itemtype'], $options['items_id'])
            && is_string($options['itemtype'])
            && in_array($options['itemtype'], Simcard_Item::getClasses(), true)
        ) {
            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __s('Associated element') . "</td>";
            echo "<td>";
            $item = new $options['itemtype']();
            $item->getFromDB((int) $options['items_id']);
            echo $item->getLink();
            echo "</td>";
            echo "<td colspan='2'></td></tr>\n";
            echo "<input type='hidden' name='_itemtype' value='" . htmlescape($options['itemtype']) . "'>";
            echo "<input type='hidden' name='_items_id' value='" . (int) $options['items_id'] . "'>";
        }

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __s('Name') . "</td>";
        echo "<td>";
        echo "<input type='text' id='name' class='form-control' name='name' value='" . htmlescape($this->fields['name']) . "' />";
        echo "</td>";
        echo "<td>" . __s('Status') . "</td>";
        echo "<td>";
        Dropdown::show('State', ['value' => $this->fields['states_id']]);
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __s('Location') . "</td>";
        echo "<td>";
        Dropdown::show('Location', [
            'value'  => $this->fields['locations_id'],
            'entity' => $this->fields['entities_id'],
        ]);
        echo "</td>";
        echo "<td>" . __s('Type of SIM card', 'simcard') . "</td>";
        echo "<td>";
        Dropdown::show(
            SimcardType::class,
            ['value' => $this->fields['plugin_simcard_simcardtypes_id']]
        );
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __s('Technician in charge of the hardware') . "</td>";
        echo "<td>";
        User::dropdown([
            'name'   => 'users_id_tech',
            'value'  => $this->fields['users_id_tech'],
            'right'  => 'own_ticket',
            'entity' => $this->fields['entities_id'],
            'rand'   => mt_rand(),
        ]);
        echo "</td>";
        echo "<td>" . __s('Size', 'simcard') . "</td>";
        echo "<td>";
        Dropdown::show(
            SimcardSize::class,
            ['value' => $this->fields['plugin_simcard_simcardsizes_id']]
        );
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __s('Group in charge of the hardware') . "</td>";
        echo "<td>";
        Group::dropdown([
            'name'      => 'groups_id_tech',
            'value'     => $this->fields['groups_id_tech'],
            'entity'    => $this->fields['entities_id'],
            'condition' => ['is_assign' => 1],
            'rand'      => mt_rand(),
        ]);
        echo "</td>";

        echo "<td>" . __s('Voltage', 'simcard') . "</td>";
        echo "<td>";
        Dropdown::show(
            SimcardVoltage::class,
            ['value' => $this->fields['plugin_simcard_simcardvoltages_id']]
        );
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __s('Provider', 'simcard') . "</td>";
        echo "<td>";
        Dropdown::show(
            PhoneOperator::class,
            ['value' => $this->fields['plugin_simcard_phoneoperators_id']]
        );
        echo "</td>";

        echo "<td>" . __s('Associable items to a ticket') . "&nbsp;:</td><td>";
        Dropdown::showYesNo('is_helpdesk_visible', $this->fields['is_helpdesk_visible']);
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __s('User') . "</td>";
        echo "<td>";
        User::dropdown([
            'name'   => 'users_id',
            'value'  => $this->fields['users_id'],
            'right'  => 'all',
            'entity' => $this->fields['entities_id'],
            'rand'   => mt_rand(),
        ]);
        echo "</td>";

        echo "<input type='hidden' name='is_global' value='1'>";

        echo "<td>" . __s('Inventory number') . "</td>";
        echo "<td>";
        echo "<input type='text' id='otherserial' class='form-control' name='otherserial' value='" . htmlescape($this->fields['otherserial']) . "' />";
        echo "</td></tr>\n";
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __s('Group') . "</td>";
        echo "<td>";
        Group::dropdown([
            'name'      => 'groups_id',
            'value'     => $this->fields['groups_id'],
            'entity'    => $this->fields['entities_id'],
            'condition' => ['is_assign' => 1],
            'rand'      => mt_rand(),
        ]);

        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __s('Phone number', 'simcard') . "</td>";
        echo "<td>";
        echo "<input type='text' id='phonenumber' class='form-control' name='phonenumber' value='" . htmlescape($this->fields['phonenumber']) . "' />";
        echo "</td>";
        echo "<td rowspan='6'>" . __s('Comments') . "</td>";
        echo "<td rowspan='6' class='middle'>";
        echo "<textarea cols='45' rows='15' name='comment' >" . htmlescape($this->fields['comment'] ?? '') . "</textarea>";
        echo "</td></tr>\n";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __s('IMSI', 'simcard') . "</td>";
        echo "<td>";
        echo "<input type='text' id='serial' class='form-control' name='serial' value='" . htmlescape($this->fields['serial']) . "' />";
        echo "</td></tr>\n";

        // Only show PIN and PUK code to users who can write (these informations are highly sensitive)
        if (self::canUpdate()) {
            $pin_puk_labels = [
                'pin'  => __s('Pin 1', 'simcard'),
                'pin2' => __s('Pin 2', 'simcard'),
                'puk'  => __s('Puk 1', 'simcard'),
                'puk2' => __s('Puk 2', 'simcard'),
            ];
            foreach ($pin_puk_labels as $field => $label) {
                echo "<tr class='tab_bg_1'>";
                echo "<td>" . $label . "</td>";
                echo "<td>";
                echo "<input type='text' id='" . $field . "' class='form-control' name='" . $field . "' value='" . htmlescape($this->fields[$field]) . "' />";
                echo "</td></tr>\n";
            }
        }

        $this->showFormButtons($options);

        return true;
    }

    public function prepareInputForAdd($input)
    {
        if (isset($input['id']) && $input['id'] > 0) {
            $input['_oldID'] = $input['id'];
        }
        unset($input['id']);
        unset($input['withtemplate']);

        return $input;
    }

    public function post_addItem()
    {
        if (
            isset($this->input['_itemtype'], $this->input['_items_id'])
            && is_string($this->input['_itemtype'])
            && in_array($this->input['_itemtype'], Simcard_Item::getClasses(), true)
        ) {
            $simcard_item = new Simcard_Item();
            $link = [
                'plugin_simcard_simcards_id' => $this->getID(),
                'itemtype'                   => $this->input['_itemtype'],
                'items_id'                   => (int) $this->input['_items_id'],
            ];
            if ($simcard_item->can(-1, CREATE, $link)) {
                $simcard_item->add($link);
            }
        }
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id' => 'common',
            'name' => __('SIM card', 'simcard')
        ];

        $tab[] = [
            'id' => '1',
            'table' => self::getTable(),
            'field' => 'name',
            'name' => __('Name'),
            'datatype' => 'itemlink',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => '2',
            'table' => self::getTable(),
            'field' => 'id',
            'name' => __('ID'),
            'datatype' => 'number',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => '403',
            'table' => 'glpi_plugin_simcard_simcardtypes',
            'field' => 'name',
            'name' => __('Type'),
            'datatype' => 'dropdown',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => '404',
            'table' => self::getTable(),
            'field' => 'serial',
            'name' => __('IMSI', 'simcard'),
            'datatype' => 'text',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => '405',
            'table' => self::getTable(),
            'field' => 'otherserial',
            'name' => __('Inventory number'),
            'datatype' => 'text',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => '406',
            'table' => self::getTable(),
            'field' => 'comment',
            'name' => __('Comments'),
            'datatype' => 'text'
        ];

        /*
        $tab[3]['checktype']       = 'text';
        $tab[3]['displaytype']     = 'dropdown';
        $tab[3]['injectable']      = true;

        $tab[91]['injectable']     = false;
        $tab[93]['injectable']     = false;
        */

        $tab[] = [
            'id' => '407',
            'table' => self::getTable(),
            'field' => 'date_mod',
            'name' => __('Last update'),
            'datatype' => 'datetime',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => '408',
            'table' => 'glpi_plugin_simcard_simcardvoltages',
            'field' => 'name',
            'name' => __('Voltage', 'simcard'),
            'datatype' => 'dropdown',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => '409',
            'table' => 'glpi_users',
            'field' => 'name',
            'linkfield' => 'users_id_tech',
            'name' => __('Technician in charge of the hardware'),
            'datatype' => 'dropdown',
            'right' => 'own_ticket'
        ];

        $tab[] = [
            'id' => '410',
            'table' => 'glpi_plugin_simcard_simcardsizes',
            'field' => 'name',
            'name' => __('Size', 'simcard'),
            'datatype' => 'dropdown',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => '411',
            'table' => 'glpi_plugin_simcard_phoneoperators',
            'field' => 'name',
            'name' => __('Provider', 'simcard'),
            'datatype' => 'dropdown',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => '412',
            'table' => self::getTable(),
            'field' => 'phonenumber',
            'name' => __('Phone number', 'simcard'),
            'datatype' => 'string',
            'massiveaction' => false
        ];

        if (self::canUpdate()) {
            $tab[] = [
                'id' => '413',
                'table' => self::getTable(),
                'field' => 'pin',
                'name' => __('Pin 1', 'simcard'),
                'datatype' => 'string',
                'massiveaction' => false
            ];

            $tab[] = [
                'id' => '414',
                'table' => self::getTable(),
                'field' => 'puk',
                'name' => __('Puk 1', 'simcard'),
                'datatype' => 'string',
                'massiveaction' => false
            ];

            $tab[] = [
                'id' => '415',
                'table' => self::getTable(),
                'field' => 'pin2',
                'name' => __('Pin 2', 'simcard'),
                'datatype' => 'string',
                'massiveaction' => false
            ];

            $tab[] = [
                'id' => '416',
                'table' => self::getTable(),
                'field' => 'puk2',
                'name' => __('Puk 2', 'simcard'),
                'datatype' => 'string',
                'massiveaction' => false
            ];
        }

        $tab[] = [
            'id' => '31',
            'table' => 'glpi_states',
            'field' => 'completename',
            'name' => __('Status'),
            'datatype' => 'dropdown'
        ];

        $tab[] = [
            'id' => '71',
            'table' => 'glpi_groups',
            'field' => 'completename',
            'name' => __('Group'),
            'datatype' => 'dropdown'
        ];

        $tab[] = [
            'id' => '49',
            'table' => 'glpi_groups',
            'field' => 'completename',
            'linkfield' => 'groups_id_tech',
            'name' => __('Group in charge of the hardware'),
            'condition' => ['is_assign' => 1],
            'datatype' => 'dropdown'
        ];

        $tab[] = [
            'id' => '70',
            'table' => 'glpi_users',
            'field' => 'name',
            'name' => __('User'),
            'datatype' => 'dropdown',
            'right' => 'all'
        ];

        $tab[] = [
            'id' => '80',
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __('Entity'),
            'massiveaction' => false,
            'datatype' => 'dropdown'
        ];

        $tab[] = [
            'id' => '90',
            'table' => self::getTable(),
            'field' => 'notepad',
            'name' => __('Notes'),
            'linkfield' => 'notepad',
            'datatype' => 'text',
            'massiveaction' => false
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
              `entities_id` int {$default_key_sign} NOT NULL DEFAULT '0',
              `name` varchar(255) NOT NULL DEFAULT '',
              `phonenumber` varchar(255) NOT NULL DEFAULT '',
              `serial` varchar(255) NOT NULL DEFAULT '',
              `pin` varchar(255) NOT NULL DEFAULT '',
              `pin2` varchar(255) NOT NULL DEFAULT '',
              `puk` varchar(255) NOT NULL DEFAULT '',
              `puk2` varchar(255) NOT NULL DEFAULT '',
              `otherserial` varchar(255) NOT NULL DEFAULT '',
              `states_id` int {$default_key_sign} NOT NULL DEFAULT '0',
              `locations_id` int {$default_key_sign} NOT NULL DEFAULT '0',
              `users_id` int {$default_key_sign} NOT NULL DEFAULT '0',
              `users_id_tech` int {$default_key_sign} NOT NULL DEFAULT '0',
              `groups_id` int {$default_key_sign} NOT NULL DEFAULT '0',
              `groups_id_tech` int {$default_key_sign} NOT NULL DEFAULT '0',
              `plugin_simcard_phoneoperators_id` int {$default_key_sign} NOT NULL DEFAULT '0',
              `manufacturers_id` int {$default_key_sign} NOT NULL DEFAULT '0',
              `plugin_simcard_simcardsizes_id` int {$default_key_sign} NOT NULL DEFAULT '0',
              `plugin_simcard_simcardvoltages_id` int {$default_key_sign} NOT NULL DEFAULT '0',
              `plugin_simcard_simcardtypes_id` int {$default_key_sign} NOT NULL DEFAULT '0',
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
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
        }
        $migration->updateDisplayPrefs([
            self::class => [1, 412, 80, 411, 410],
        ], [], true);
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

        // Remove unicity constraints on simcards
        FieldUnicity::deleteForItemtype(self::class);

        foreach ([Notepad::class, DisplayPreference::class, Contract_Item::class, Infocom::class, Fieldblacklist::class, Document_Item::class, Log::class, SavedSearch::class] as $itemtype) {
            $item = new $itemtype();
            $item->deleteByCriteria(['itemtype' => self::class]);
        }

        $plugin = new Plugin();
        if ($plugin->isActivated('datainjection') && class_exists('PluginDatainjectionModel')) {
            \PluginDatainjectionModel::clean(['itemtype' => self::class]);
        }

        $table = self::getTable();
        $DB->doQuery("DROP TABLE IF EXISTS `$table`");
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof self) {
            return _n('SIM card', 'SIM cards', 2, 'simcard');
        }
        return '';
    }

    /**
     * Show tab content for a simcard item
     *
     * @param CommonGLPI $item
     * @param int        $tabnum
     * @param int        $withtemplate
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof self) {
            $self = new self();
            $self->showForm($item->getField('id'));
        }
        return true;
    }

    /**
     * Types that could be linked to a simcard
     *
     * @param bool $all all types, or only allowed ones
     *
     * @return array of types
     **/
    public static function getTypes($all = false)
    {
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

    public static function getIcon()
    {
        return 'ti ti-device-sim';
    }

    /**
     * Actions done when item is deleted from the database
     *
     * @return void
     * */
    public function cleanDBonPurge()
    {
        $link = new Simcard_Item();
        $link->cleanDBonItemDelete(self::getType(), $this->getID());
    }

    /**
     * Delete an item in the database.
     *
     * @param array $input   the _POST vars returned by the item form when press delete
     * @param bool  $force   force deletion (default false)
     * @param bool  $history do history log ? (default true)
     *
     * @return bool true on success
     * @see CommonDBTM::delete()
     */
    public function delete(array $input, $force = false, $history = true)
    {
        $deleteSuccessful = parent::delete($input, $force, $history);
        if ($deleteSuccessful && $force) {
            $notepad = new Notepad();
            $notepad->deleteByCriteria([
                'itemtype' => self::class,
                'items_id' => $input['id'],
            ]);
        }
        return $deleteSuccessful;
    }

    /**
     * @see CommonDBTM::getSpecificMassiveActions()
     * */
    public function getSpecificMassiveActions($checkitem = null)
    {
        $isadmin = static::canUpdate();
        $actions = parent::getSpecificMassiveActions($checkitem);

        if ($_SESSION['glpiactiveprofile']['interface'] === 'central') {
            if ($isadmin) {
                if (Session::haveRight('transfer', READ) && Session::isMultiEntitiesMode()) {
                    $actions[self::class . MassiveAction::CLASS_ACTION_SEPARATOR . 'transfer'] = __('Transfer');
                }
            }
        }
        return $actions;
    }

    /**
     * @see CommonDBTM::showMassiveActionsSubForm()
     * */
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case 'transfer':
                Dropdown::show('Entity');
                echo Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
                return true;
        }
        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     * */
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        switch ($ma->getAction()) {
            case 'transfer':
                $input = $ma->getInput();
                $entity = (int) $input['entities_id'];
                if ($item instanceof self) {
                    $dropdowns = [
                        'plugin_simcard_simcardtypes_id'    => SimcardType::class,
                        'plugin_simcard_simcardsizes_id'    => SimcardSize::class,
                        'plugin_simcard_simcardvoltages_id' => SimcardVoltage::class,
                        'plugin_simcard_phoneoperators_id'  => PhoneOperator::class,
                    ];
                    foreach ($ids as $key) {
                        if (!$item->can($key, UPDATE)) {
                            $ma->itemDone($item->getType(), $key, MassiveAction::ACTION_NORIGHT);
                            continue;
                        }
                        $values = ['id' => $key, 'entities_id' => $entity];
                        foreach ($dropdowns as $field => $dropdown) {
                            $new_id = $dropdown::transfer((int) $item->fields[$field], $entity);
                            if ($new_id > 0) {
                                $values[$field] = $new_id;
                            }
                        }

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

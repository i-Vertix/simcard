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

function plugin_simcard_install()
{
    global $DB;

    include_once(GLPI_ROOT . "/plugins/simcard/inc/profile.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/simcard.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/simcardsize.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/simcardvoltage.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/simcardtype.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/phoneoperator.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/simcard_item.class.php");

    $migration = new Migration(PLUGIN_SIMCARD_VERSION);
    if (!$DB->TableExists(PluginSimcardSimcard::getTable())) {
        // Installation of the plugin
        PluginSimcardProfile::install($migration);
        PluginSimcardSimcard::install($migration);
        PluginSimcardSimcardSize::install($migration);
        PluginSimcardSimcardVoltage::install($migration);
        PluginSimcardSimcardType::install($migration);
        PluginSimcardPhoneOperator::install($migration);
        PluginSimcardSimcard_Item::install($migration);
    } else {
        // Updating the plugin
        PluginSimcardProfile::upgrade($migration);
        PluginSimcardSimcard::upgrade($migration);
        PluginSimcardSimcardSize::upgrade($migration);
        PluginSimcardSimcardVoltage::upgrade($migration);
        PluginSimcardSimcardType::upgrade($migration);
        PluginSimcardPhoneOperator::upgrade($migration);
        PluginSimcardSimcard_Item::upgrade($migration);
    }
    if ($DB->tableExists('glpi_plugin_simcard_configs')) {
        $migration->dropTable("glpi_plugin_simcard_configs");
    }
    $migration->executeMigration();
    return true;
}

function plugin_simcard_uninstall()
{
    global $DB;
    $migration = new Migration(PLUGIN_SIMCARD_VERSION);
    include_once(GLPI_ROOT . "/plugins/simcard/inc/profile.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/simcard.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/simcardsize.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/simcardvoltage.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/simcardtype.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/phoneoperator.class.php");
    include_once(GLPI_ROOT . "/plugins/simcard/inc/simcard_item.class.php");

    PluginSimcardProfile::uninstall();
    PluginSimcardSimcard::uninstall();
    PluginSimcardSimcardSize::uninstall();
    PluginSimcardSimcardVoltage::uninstall();
    PluginSimcardSimcardType::uninstall();
    PluginSimcardPhoneOperator::uninstall();
    PluginSimcardSimcard_Item::uninstall();
    if ($DB->tableExists("glpi_plugin_simcard_configs")) {
        $migration->dropTable("glpi_plugin_simcard_configs");
    }
    $migration->executeMigration();
    return true;
}

// Define dropdown relations
function plugin_simcard_getDatabaseRelations()
{

    $plugin = new Plugin();

    if ($plugin->isActivated("simcard")) {
        return [
            "glpi_plugin_simcard_simcards" => [
                "glpi_plugin_simcard_simcards_items" => "plugin_simcard_simcards_id"
            ],
            "glpi_plugin_simcard_simcardsizes" => [
                "glpi_plugin_simcard_simcards" => "plugin_simcard_simcardsizes_id"
            ],
            "glpi_plugin_simcard_simcardvoltages" => [
                "glpi_plugin_simcard_simcards" => "plugin_simcard_simcardvoltages_id"
            ],
            "glpi_plugin_simcard_phoneoperators" => [
                "glpi_plugin_simcard_simcards" => "plugin_simcard_phoneoperators_id"
            ],
            "glpi_plugin_simcard_simcardtypes" => [
                "glpi_plugin_simcard_simcards" => "plugin_simcard_simcardtypes_id"
            ],
            "glpi_users" => [
                "glpi_plugin_simcard_simcards" => ["users_id", "users_id_tech"],
            ],
            "glpi_states" => [
                "glpi_plugin_simcard_simcards" => "states_id",
            ],
            "glpi_locations" => [
                "glpi_plugin_simcard_simcards" => "locations_id",
            ],
            "glpi_groups" => [
                "glpi_plugin_simcard_simcards" => ["groups_id", "groups_id_tech"],
            ],
            "glpi_manufacturers" => [
                "glpi_plugin_simcard_simcards" => "manufacturers_id",
            ],
            "glpi_entities" => [
                "glpi_plugin_simcard_simcards" => "entities_id",
            ]
        ];
        //"glpi_profiles" => array ("glpi_plugin_simcard_profiles" => "profiles_id"));
    } else {
        return array();
    }
}


// Define Dropdown tables to be manage in GLPI :
function plugin_simcard_getDropdown()
{
    global $LANG;

    $plugin = new Plugin();
    if ($plugin->isActivated("simcard")) {
        return array(
            'PluginSimcardSimcardSize' => __('Size', 'simcard'),
            'PluginSimcardPhoneOperator' => __('Provider', 'simcard'),
            'PluginSimcardSimcardVoltage' => __('Voltage', 'simcard'),
            'PluginSimcardSimcardType' => __('Type of SIM card', 'simcard')
        );
    } else {
        return array();
    }
}

function plugin_simcard_AssignToTicket($types)
{
    global $LANG;

    if (Session::haveRight(PluginSimcardProfile::RIGHT_SIMCARD_SIMCARD, PluginSimcardProfile::SIMCARD_ASSOCIATE_TICKET)) {
        $types['PluginSimcardSimcard'] = 'SIM Cards';
    }

    return $types;
}

//force groupby for multible links to items

function plugin_simcard_forceGroupBy($type)
{

    //   return true;
    switch ($type) {
        case 'PluginSimcardSimcard':
            return true;
    }
    return false;
}

function plugin_simcard_getAddSearchOptions($itemtype)
{
    global $LANG;

    $sopt = array();

    $reservedTypeIndex = 10126;

    if (in_array($itemtype, PluginSimcardSimcard_Item::getClasses())) {
        if (PluginSimcardSimcard::canView()) {
            $sopt[$reservedTypeIndex]['table'] = 'glpi_plugin_simcard_simcards';
            $sopt[$reservedTypeIndex]['field'] = 'name';
            $sopt[$reservedTypeIndex]['name'] = _sn('SIM card', 'SIM cards', 2, 'simcard') . " - " . __s('Name');
            $sopt[$reservedTypeIndex]['forcegroupby'] = true;
            $sopt[$reservedTypeIndex]['massiveaction'] = false;
            $sopt[$reservedTypeIndex]['datatype'] = 'itemlink';
            $sopt[$reservedTypeIndex]['itemlink_type'] = 'PluginSimcardSimcard';
            $sopt[$reservedTypeIndex]['joinparams'] = array('beforejoin'
            => array(
                    'table' => 'glpi_plugin_simcard_simcards_items',
                    'joinparams' => array('jointype' => 'itemtype_item')
                ));
            $reservedTypeIndex++;
            $sopt[$reservedTypeIndex]['table'] = 'glpi_plugin_simcard_simcards';
            $sopt[$reservedTypeIndex]['field'] = 'phonenumber';
            $sopt[$reservedTypeIndex]['name'] = _sn('SIM card', 'SIM cards', 2, 'simcard') . " - " . __s('Phone number', 'simcard');
            $sopt[$reservedTypeIndex]['massiveaction'] = false;
            $sopt[$reservedTypeIndex]['forcegroupby'] = true;
            $sopt[$reservedTypeIndex]['joinparams'] = array('beforejoin'
            => array(
                    'table' => 'glpi_plugin_simcard_simcards_items',
                    'joinparams' => array('jointype' => 'itemtype_item')
                ));
            $reservedTypeIndex++;
            $sopt[$reservedTypeIndex]['table'] = 'glpi_plugin_simcard_simcards';
            $sopt[$reservedTypeIndex]['field'] = 'serial';
            $sopt[$reservedTypeIndex]['name'] = _sn('SIM card', 'SIM cards', 2, 'simcard') . " - " . __s('IMSI', 'simcard');
            $sopt[$reservedTypeIndex]['massiveaction'] = false;
            $sopt[$reservedTypeIndex]['forcegroupby'] = true;
            $sopt[$reservedTypeIndex]['joinparams'] = array('beforejoin'
            => array(
                    'table' => 'glpi_plugin_simcard_simcards_items',
                    'joinparams' => array('jointype' => 'itemtype_item')
                ));
        }
    }
    return $sopt;
}


// Hook done on purge item case

function plugin_item_purge_simcard($item)
{

    $temp = new PluginSimcardSimcard_Item();
    $temp->deleteByCriteria(array(
        'itemtype' => get_class($item),
        'items_id' => $item->getField('id')
    ));
    return true;
}

function plugin_datainjection_populate_simcard()
{
    global $INJECTABLE_TYPES;

    $INJECTABLE_TYPES['PluginSimcardSimcardInjection'] = 'simcard';
}

/**
 *
 * Determine if the plugin should be installed or upgraded
 *
 * Returns 0 if the plugin is not yet installed
 * Returns 1 if the plugin is already installed
 *
 * @since 1.3
 */
function plugin_simcard_postinit()
{
    global $UNINSTALL_TYPES, $ORDER_TYPES, $ALL_CUSTOMFIELDS_TYPES, $DB;
    $plugin = new Plugin();
    if ($plugin->isInstalled('uninstall') && $plugin->isActivated('uninstall')) {
        $UNINSTALL_TYPES[] = 'PluginSimcardSimcard';
    }
    if ($plugin->isInstalled('order') && $plugin->isActivated('order')) {
        $ORDER_TYPES[] = 'PluginSimcardSimcard';
    }
}

/**
 * Update helpdesk_item_type in a profile if a ProfileRight changes or is created
 *
 * Add or remove simcard item type to match the status of "associable to tickets" in simcard's right
 *
 * @since 1.4.1
 */
function plugin_simcard_profileRightUpdate($item)
{
    if ($_SESSION['glpiactiveprofile']['id'] == $item->fields['profiles_id']) {
        if ($item->fields['name'] == PluginSimcardProfile::RIGHT_SIMCARD_SIMCARD) {
            $profile = new Profile();
            $profile->getFromDB($item->fields['profiles_id']);
            $helpdeskItemTypes = json_decode($profile->fields['helpdesk_item_type'], true);
            if (!is_array($helpdeskItemTypes)) {
                $helpdeskItemTypes = array();
            }
            $index = array_search('PluginSimcardSimcard', $helpdeskItemTypes);
            if ($item->fields['rights'] & PluginSimcardProfile::SIMCARD_ASSOCIATE_TICKET) {
                if ($index === false) {
                    $helpdeskItemTypes[] = 'PluginSimcardSimcard';
                    if ($_SESSION['glpiactiveprofile']['id'] == $profile->fields['id']) {
                        $_SESSION['glpiactiveprofile']['helpdesk_item_type'][] = 'PluginSimcardSimcard';
                    }
                }
            } else {
                if ($index !== false) {
                    unset($helpdeskItemTypes[$index]);
                    if ($_SESSION['glpiactiveprofile']['id'] == $profile->fields['id']) {
                        // Just in case this is not the same index in the session vars
                        $index = array_search('PluginSimcardSimcard', $_SESSION['glpiactiveprofile']['helpdesk_item_type']);
                        if ($index !== false) {
                            unset($_SESSION['glpiactiveprofile']['helpdesk_item_type'][$index]);
                        }
                    }
                }
            }
            $tmp = array(
                'id' => $profile->fields['id'],
                'helpdesk_item_type' => json_encode($helpdeskItemTypes)
            );
            $profile->update($tmp, false);
        }
    }
}

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

use GlpiPlugin\Simcard\PhoneOperator;
use GlpiPlugin\Simcard\Profile;
use GlpiPlugin\Simcard\Simcard;
use GlpiPlugin\Simcard\Simcard_Item;
use GlpiPlugin\Simcard\SimcardInjection;
use GlpiPlugin\Simcard\SimcardSize;
use GlpiPlugin\Simcard\SimcardType;
use GlpiPlugin\Simcard\SimcardVoltage;

function plugin_simcard_install(): bool
{
    global $DB;

    $migration = new Migration(PLUGIN_SIMCARD_VERSION);
    if (!$DB->tableExists(Simcard::getTable())) {
        // Installation of the plugin
        Profile::install($migration);
        Simcard::install($migration);
        SimcardSize::install($migration);
        SimcardVoltage::install($migration);
        SimcardType::install($migration);
        PhoneOperator::install($migration);
        Simcard_Item::install($migration);
    } else {
        // Updating the plugin
        Profile::upgrade($migration);
        Simcard::upgrade($migration);
        SimcardSize::upgrade($migration);
        SimcardVoltage::upgrade($migration);
        SimcardType::upgrade($migration);
        PhoneOperator::upgrade($migration);
        Simcard_Item::upgrade($migration);
    }
    if ($DB->tableExists('glpi_plugin_simcard_configs')) {
        $migration->dropTable('glpi_plugin_simcard_configs');
    }
    $migration->executeMigration();
    return true;
}

function plugin_simcard_uninstall(): bool
{
    global $DB;

    $migration = new Migration(PLUGIN_SIMCARD_VERSION);

    Profile::uninstall();
    Simcard::uninstall();
    SimcardSize::uninstall();
    SimcardVoltage::uninstall();
    SimcardType::uninstall();
    PhoneOperator::uninstall();
    Simcard_Item::uninstall();
    if ($DB->tableExists('glpi_plugin_simcard_configs')) {
        $migration->dropTable('glpi_plugin_simcard_configs');
    }
    $migration->executeMigration();
    return true;
}

// Define dropdown relations
function plugin_simcard_getDatabaseRelations(): array
{
    if (Plugin::isPluginActive('simcard')) {
        return [
            'glpi_plugin_simcard_simcards' => [
                'glpi_plugin_simcard_simcards_items' => 'plugin_simcard_simcards_id',
            ],
            'glpi_plugin_simcard_simcardsizes' => [
                'glpi_plugin_simcard_simcards' => 'plugin_simcard_simcardsizes_id',
            ],
            'glpi_plugin_simcard_simcardvoltages' => [
                'glpi_plugin_simcard_simcards' => 'plugin_simcard_simcardvoltages_id',
            ],
            'glpi_plugin_simcard_phoneoperators' => [
                'glpi_plugin_simcard_simcards' => 'plugin_simcard_phoneoperators_id',
            ],
            'glpi_plugin_simcard_simcardtypes' => [
                'glpi_plugin_simcard_simcards' => 'plugin_simcard_simcardtypes_id',
            ],
            'glpi_users' => [
                'glpi_plugin_simcard_simcards' => ['users_id', 'users_id_tech'],
            ],
            'glpi_states' => [
                'glpi_plugin_simcard_simcards' => 'states_id',
            ],
            'glpi_locations' => [
                'glpi_plugin_simcard_simcards' => 'locations_id',
            ],
            'glpi_groups' => [
                'glpi_plugin_simcard_simcards' => ['groups_id', 'groups_id_tech'],
            ],
            'glpi_manufacturers' => [
                'glpi_plugin_simcard_simcards' => 'manufacturers_id',
            ],
            'glpi_entities' => [
                'glpi_plugin_simcard_simcards' => 'entities_id',
            ],
        ];
    }
    return [];
}

// Define Dropdown tables to be manage in GLPI :
function plugin_simcard_getDropdown(): array
{
    if (Plugin::isPluginActive('simcard')) {
        return [
            SimcardSize::class     => __('Size', 'simcard'),
            PhoneOperator::class   => __('Provider', 'simcard'),
            SimcardVoltage::class  => __('Voltage', 'simcard'),
            SimcardType::class     => __('Type of SIM card', 'simcard'),
        ];
    }
    return [];
}

function plugin_simcard_AssignToTicket(array $types): array
{
    if (Session::haveRight(Profile::RIGHT_SIMCARD_SIMCARD, Profile::SIMCARD_ASSOCIATE_TICKET)) {
        $types[Simcard::class] = 'SIM Cards';
    }

    return $types;
}

// force groupby for multiple links to items
function plugin_simcard_forceGroupBy(string $itemtype): bool
{
    return $itemtype === Simcard::class;
}

function plugin_simcard_getAddSearchOptions(string $itemtype): array
{
    $sopt = [];

    $reservedTypeIndex = 10126;

    if (in_array($itemtype, Simcard_Item::getClasses(), true)) {
        if (Simcard::canView()) {
            $joinparams = [
                'beforejoin' => [
                    'table'      => 'glpi_plugin_simcard_simcards_items',
                    'joinparams' => ['jointype' => 'itemtype_item'],
                ],
            ];
            $prefix = _n('SIM card', 'SIM cards', 2, 'simcard') . ' - ';

            $sopt[$reservedTypeIndex] = [
                'table'         => 'glpi_plugin_simcard_simcards',
                'field'         => 'name',
                'name'          => $prefix . __('Name'),
                'forcegroupby'  => true,
                'massiveaction' => false,
                'datatype'      => 'itemlink',
                'itemlink_type' => Simcard::class,
                'joinparams'    => $joinparams,
            ];
            $reservedTypeIndex++;
            $sopt[$reservedTypeIndex] = [
                'table'         => 'glpi_plugin_simcard_simcards',
                'field'         => 'phonenumber',
                'name'          => $prefix . __('Phone number', 'simcard'),
                'massiveaction' => false,
                'forcegroupby'  => true,
                'joinparams'    => $joinparams,
            ];
            $reservedTypeIndex++;
            $sopt[$reservedTypeIndex] = [
                'table'         => 'glpi_plugin_simcard_simcards',
                'field'         => 'serial',
                'name'          => $prefix . __('IMSI', 'simcard'),
                'massiveaction' => false,
                'forcegroupby'  => true,
                'joinparams'    => $joinparams,
            ];
        }
    }
    return $sopt;
}

// Hook done on purge item case
function plugin_item_purge_simcard(CommonDBTM $item): void
{
    $temp = new Simcard_Item();
    $temp->deleteByCriteria([
        'itemtype' => $item::class,
        'items_id' => $item->getID(),
    ]);
}

function plugin_datainjection_populate_simcard(): void
{
    global $INJECTABLE_TYPES;

    $INJECTABLE_TYPES[SimcardInjection::class] = 'simcard';
}

/**
 * Register the SIM card itemtype with other plugins (Uninstall, Order), if they are active
 */
function plugin_simcard_postinit(): void
{
    global $UNINSTALL_TYPES, $ORDER_TYPES;

    if (Plugin::isPluginActive('uninstall')) {
        $UNINSTALL_TYPES[] = Simcard::class;
    }
    if (Plugin::isPluginActive('order')) {
        $ORDER_TYPES[] = Simcard::class;
    }
}

/**
 * Update helpdesk_item_type in a profile if a ProfileRight changes or is created
 *
 * Add or remove simcard item type to match the status of "associable to tickets" in simcard's right
 */
function plugin_simcard_profileRightUpdate(ProfileRight $item): void
{
    if ($item->fields['name'] !== Profile::RIGHT_SIMCARD_SIMCARD) {
        return;
    }

    $profile = new \Profile();
    if (!$profile->getFromDB($item->fields['profiles_id'])) {
        return;
    }

    // The DB stores helpdesk_item_type as JSON; Profile::prepareInputForUpdate() expects an array
    $helpdeskItemTypes = $profile->fields['helpdesk_item_type'] ?? [];
    if (!is_array($helpdeskItemTypes)) {
        $helpdeskItemTypes = importArrayFromDB($helpdeskItemTypes);
    }
    if (!is_array($helpdeskItemTypes)) {
        $helpdeskItemTypes = [];
    }

    $is_active_profile = isset($_SESSION['glpiactiveprofile']['id'])
        && $_SESSION['glpiactiveprofile']['id'] == $item->fields['profiles_id'];

    $index = array_search(Simcard::class, $helpdeskItemTypes, true);
    if ($item->fields['rights'] & Profile::SIMCARD_ASSOCIATE_TICKET) {
        if ($index === false) {
            $helpdeskItemTypes[] = Simcard::class;
        }
        if (
            $is_active_profile
            && !in_array(Simcard::class, $_SESSION['glpiactiveprofile']['helpdesk_item_type'] ?? [], true)
        ) {
            $_SESSION['glpiactiveprofile']['helpdesk_item_type'][] = Simcard::class;
        }
    } else {
        if ($index !== false) {
            unset($helpdeskItemTypes[$index]);
        }
        if ($is_active_profile) {
            // Not necessarily the same index as in the profile
            $session_index = array_search(Simcard::class, $_SESSION['glpiactiveprofile']['helpdesk_item_type'] ?? [], true);
            if ($session_index !== false) {
                unset($_SESSION['glpiactiveprofile']['helpdesk_item_type'][$session_index]);
            }
        }
    }

    $profile->update([
        'id'                 => $profile->fields['id'],
        'helpdesk_item_type' => array_values($helpdeskItemTypes), // encoded by Profile::prepareInputForUpdate()
    ], false);
}

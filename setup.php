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

use Glpi\Plugin\Hooks;
use GlpiPlugin\Simcard\PhoneOperator;
use GlpiPlugin\Simcard\Profile;
use GlpiPlugin\Simcard\Simcard;
use GlpiPlugin\Simcard\Simcard_Item;
use GlpiPlugin\Simcard\SimcardInjection;
use GlpiPlugin\Simcard\SimcardSize;
use GlpiPlugin\Simcard\SimcardType;
use GlpiPlugin\Simcard\SimcardVoltage;

define('PLUGIN_SIMCARD_VERSION', '2.0.0');

// Minimal GLPI version, inclusive
define('PLUGIN_SIMCARD_MIN_GLPI', '12.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_SIMCARD_MAX_GLPI', '12.0.99');

/**
 * Class names used before 2.0.0 => current namespaced class names.
 * Kept so that third-party code and stored itemtypes still resolve (deprecated).
 */
define('PLUGIN_SIMCARD_LEGACY_CLASSES', [
    'PluginSimcardSimcard'          => Simcard::class,
    'PluginSimcardSimcard_Item'     => Simcard_Item::class,
    'PluginSimcardSimcardSize'      => SimcardSize::class,
    'PluginSimcardSimcardVoltage'   => SimcardVoltage::class,
    'PluginSimcardSimcardType'      => SimcardType::class,
    'PluginSimcardPhoneOperator'    => PhoneOperator::class,
    'PluginSimcardProfile'          => Profile::class,
    'PluginSimcardSimcardInjection' => SimcardInjection::class,
]);

// Lazily alias a legacy class name to its namespaced replacement on first use
spl_autoload_register(static function (string $class): void {
    $legacy_classes = PLUGIN_SIMCARD_LEGACY_CLASSES;
    if (isset($legacy_classes[$class])) {
        class_alias($legacy_classes[$class], $class);
    }
});

/**
 * Init the hooks of the plugin
 */
function plugin_init_simcard(): void
{
    global $PLUGIN_HOOKS;

    if (!Plugin::isPluginActive('simcard')) {
        return;
    }

    $PLUGIN_HOOKS[Hooks::ASSIGN_TO_TICKET]['simcard'] = true;

    // PIN and PUK codes are encrypted with the GLPI key: re-encrypted by GLPI on key change
    $PLUGIN_HOOKS[Hooks::SECURED_FIELDS]['simcard'] = [
        'glpi_plugin_simcard_simcards.pin',
        'glpi_plugin_simcard_simcards.pin2',
        'glpi_plugin_simcard_simcards.puk',
        'glpi_plugin_simcard_simcards.puk2',
    ];

    $PLUGIN_HOOKS['plugin_datainjection_populate']['simcard'] = 'plugin_datainjection_populate_simcard';

    $PLUGIN_HOOKS[Hooks::ITEM_PURGE]['simcard'] = [];
    foreach (Simcard_Item::getClasses() as $type) {
        $PLUGIN_HOOKS[Hooks::ITEM_PURGE]['simcard'][$type] = 'plugin_item_purge_simcard';
    }

    $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['simcard'][ProfileRight::class] = 'plugin_simcard_profileRightUpdate';
    $PLUGIN_HOOKS[Hooks::ITEM_ADD]['simcard'][ProfileRight::class] = 'plugin_simcard_profileRightUpdate';

    Plugin::registerClass(Simcard_Item::class, ['addtabon' => Simcard_Item::getClasses()]);
    Plugin::registerClass(Profile::class, ['addtabon' => \Profile::class]);

    Plugin::registerClass(Simcard::class, [
        'assignable_types'       => true,
        'document_types'         => true,
        'contract_types'         => true,
        'ticket_types'           => true,
        'helpdesk_visible_types' => true,
        'infocom_types'          => true,
        'unicity_types'          => true,
        'reservation_types'      => true,
        'location_types'         => true,
        'state_types'            => true,
        'globalsearch_types'     => true,
    ]);

    // if glpi is loaded
    if (Session::getLoginUserID()) {
        // Display a menu entry ?
        if (
            Simcard::canCreate()
            || Simcard::canUpdate()
            || Simcard::canDelete()
            || Simcard::canView()
        ) {
            $PLUGIN_HOOKS[Hooks::MENU_TOADD]['simcard'] = ['assets' => Simcard::class];
        }

        if (Simcard::canCreate()) {
            // use massiveaction in the plugin
            $PLUGIN_HOOKS[Hooks::USE_MASSIVE_ACTION]['simcard'] = 1;
        }

        $PLUGIN_HOOKS[Hooks::POST_INIT]['simcard'] = 'plugin_simcard_postinit';
    }
}

/**
 * Get the name and the version of the plugin
 */
function plugin_version_simcard(): array
{
    return [
        'name'         => __('Sim cards management', 'simcard'),
        'version'      => PLUGIN_SIMCARD_VERSION,
        'author'       => '<a href="https://www.pgum.eu/">PGUM s.r.l.</a>, <a href="https://github.com/i-Vertix/simcard">i-Vertix NMS</a>',
        'license'      => 'GPLv2',
        'homepage'     => 'https://github.com/i-Vertix/simcard',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_SIMCARD_MIN_GLPI,
                'max' => PLUGIN_SIMCARD_MAX_GLPI,
            ],
            'php'  => [
                'min' => '8.3',
            ],
        ],
    ];
}

/**
 * Called by GLPI on install and activation
 */
function plugin_simcard_check_config(bool $verbose = false): bool
{
    return true;
}

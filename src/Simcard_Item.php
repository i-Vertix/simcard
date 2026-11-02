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

use CommonDBRelation;
use CommonDBTM;
use CommonGLPI;
use DBConnection;
use Dropdown;
use Glpi\DBAL\QuerySubQuery;
use Html;
use Migration;
use Plugin;
use Toolbox;

// Relation between Simcard and Items (computer, phone, peripheral only)
class Simcard_Item extends CommonDBRelation
{
    // From CommonDBRelation
    public static ?string $itemtype_1 = Simcard::class;
    public static ?string $items_id_1 = 'plugin_simcard_simcards_id';

    public static ?string $itemtype_2 = 'itemtype';
    public static ?string $items_id_2 = 'items_id';

    // Itemtypes simcards may be linked to
    protected static $linkableClasses = [
        'Computer',
        'Peripheral',
        'Phone',
        'Printer',
        'NetworkEquipment',
    ];

    /**
     * Name of the type
     *
     * @param int $nb number of item in the type (default 0)
     **/
    public static function getTypeName($nb = 0)
    {
        return __('Direct Connections');
    }

    public static function countForItem(CommonDBTM $item): int
    {
        return countElementsInTable(self::getTable(), ['plugin_simcard_simcards_id' => $item->getID()]);
    }

    /**
     * Count the number of relations having the itemtype of $item
     *
     * @param CommonDBTM $item Item whose relations to simcards shall be counted
     * @return int count of relations between the item and simcards
     */
    public static function countForItemByItemtype(CommonDBTM $item): int
    {
        return countElementsInTable(self::getTable(), [
            'items_id' => $item->getID(),
            'itemtype' => $item->getType(),
        ]);
    }

    /**
     * Hook called After an item is uninstall or purge
     */
    public static function cleanForItem(CommonDBTM $item): void
    {
        $temp = new self();
        $temp->deleteByCriteria([
            'itemtype' => $item->getType(),
            'items_id' => $item->getID(),
        ]);
    }

    public static function getClasses(): array
    {
        return self::$linkableClasses;
    }

    /**
     * Declare a new itemtype to be linkable to a simcard
     */
    public static function registerItemtype(string $itemtype): void
    {
        if (!in_array($itemtype, self::$linkableClasses, true)) {
            self::$linkableClasses[] = $itemtype;
            Plugin::registerClass(self::class, ['addtabon' => $itemtype]);
        }
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
              `items_id` int {$default_key_sign} NOT NULL DEFAULT '0',
              `plugin_simcard_simcards_id` int {$default_key_sign} NOT NULL DEFAULT '0',
              `itemtype` varchar(100) NOT NULL,
              PRIMARY KEY (`id`),
              KEY `plugin_simcard_simcards_id` (`plugin_simcard_simcards_id`),
              KEY `item` (`itemtype`,`items_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
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
        $table = self::getTable();
        $DB->doQuery("DROP TABLE IF EXISTS `$table`");
    }

    public static function showForSimcard(Simcard $simcard): void
    {
        if (!$simcard->canView()) {
            return;
        }

        $results = getAllDataFromTable(self::getTable(), ['plugin_simcard_simcards_id' => $simcard->getID()]);
        $number = count($results);
        $container = 'massSimcardItem' . mt_rand();

        if (Simcard::canUpdate() && !$number) {
            echo "<div class='firstbloc'>";
            echo "<form id='items' name='items' method='post' action='" . htmlescape(Toolbox::getItemTypeFormURL(self::class)) . "'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_2'><th colspan='2'>" . __s('Connect a Device') . "</th></tr>";
            echo "<tr class='tab_bg_1'><td>";
            echo "<input type='hidden' name='plugin_simcard_simcards_id' value='" . (int) $simcard->getID() . "'>";
            Dropdown::showSelectItemFromItemtypes([
                'itemtypes'       => self::getClasses(),
                'entity_restrict' => $simcard->fields['entities_id'],
            ]);
            echo "</td><td class='center'>";
            echo "<input type='submit' name='additem' value=\"" . _sx('button', 'Connect') . "\" class='btn btn-primary'>";
            echo "</td></tr>";
            echo "</table>";
            Html::closeForm();
            echo "</div>";
        }

        echo "<div class='spaced'>";
        if (Simcard::canUpdate() && $number) {
            Html::openMassiveActionsForm($container);
            $massiveactionparams = [
                'num_displayed'    => min($_SESSION['glpilist_limit'], $number),
                'specific_actions' => ['purge' => _x('button', 'Disconnect')],
                'container'        => $container,
            ];
            Html::showMassiveActions($massiveactionparams);
        }
        echo "<table class='tab_cadre_fixehov'>";
        if (!empty($results)) {
            $header_begin = "<tr>";
            $header_top = '';
            $header_bottom = '';
            $header_end = '';

            if (Simcard::canUpdate()) {
                $header_top .= "<th width='10'>" . Html::getCheckAllAsCheckbox($container);
                $header_top .= "</th>";
                $header_bottom .= "<th width='10'>" . Html::getCheckAllAsCheckbox($container);
                $header_bottom .= "</th>";
            }

            $header_end .= "<th>" . __s('Type') . "</th>";
            $header_end .= "<th>" . __s('Entity') . "</th>";
            $header_end .= "<th>" . __s('Name') . "</th>";
            $header_end .= "<th>" . __s('Serial number') . "</th>";
            $header_end .= "<th>" . __s('Inventory number') . "</th>";
            $header_end .= "</tr>";
            echo $header_begin . $header_top . $header_end;

            foreach ($results as $data) {
                $item = getItemForItemtype($data['itemtype']);
                if (!$item instanceof CommonDBTM) {
                    continue;
                }
                $item->getFromDB($data['items_id']);
                echo "<tr class='tab_bg_1'>";

                if (Simcard::canUpdate()) {
                    echo "<td width='10'>";
                    Html::showMassiveActionCheckBox(self::class, $data['id']);
                    echo "</td>";
                }

                echo "<td>";
                echo htmlescape($item::getTypeName());
                echo "</td>";
                echo "<td>";
                echo htmlescape(Dropdown::getDropdownName('glpi_entities', $item->fields['entities_id']));
                echo "</td>";
                echo "<td>";
                echo $item->getLink();
                echo "</td>";
                echo "<td>";
                if (isset($item->fields['serial'])) {
                    echo htmlescape($item->fields['serial']);
                }
                echo "</td>";
                echo "<td>";
                if (isset($item->fields['otherserial'])) {
                    echo htmlescape($item->fields['otherserial']);
                }
                echo "</td>";
                echo "</tr>";
            }
            echo $header_begin . $header_bottom . $header_end;
        } else {
            echo "<tr><td class='tab_bg_1 b'><i>" . __s('Not connected') . "</i>";
            echo "</td></tr>";
        }
        echo "</table>";
        if (Simcard::canUpdate() && $number) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();
        }

        echo "</div>";
    }

    public static function showForItem(CommonDBTM $item): void
    {
        global $DB;

        if (!$item->canView()) {
            return;
        }

        $results = getAllDataFromTable(self::getTable(), [
            'items_id' => $item->getID(),
            'itemtype' => get_class($item),
        ]);
        $container = 'massSimcardItem' . mt_rand();

        $number = count($results);
        if (Simcard::canUpdate()) {
            echo "<div class='firstbloc'>";
            echo "<form id='items' name='items' method='post' action='" . htmlescape(Toolbox::getItemTypeFormURL(self::class)) . "'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_2'><th colspan='2'>" . __s('Connect SIM cards') . "</th></tr>";
            echo "<tr class='tab_bg_1'><td>";
            echo "<input type='hidden' name='items_id' value='" . (int) $item->getID() . "'>";
            echo "<input type='hidden' name='itemtype' value='" . htmlescape($item->getType()) . "'>";
            $used = [];
            $iterator = $DB->request([
                'SELECT' => Simcard::getTable() . '.id',
                'FROM'   => Simcard::getTable(),
                'WHERE'  => [
                    'is_template' => 0,
                    'id'          => new QuerySubQuery([
                        'SELECT' => 'plugin_simcard_simcards_id',
                        'FROM'   => self::getTable(),
                    ]),
                ],
            ]);
            foreach ($iterator as $use) {
                $used[] = $use['id'];
            }
            Dropdown::show(Simcard::class, [
                'name'   => 'plugin_simcard_simcards_id',
                'entity' => $item->fields['entities_id'],
                'used'   => $used,
            ]);
            echo "</td>";
            echo "<td class='center' width='20%'>";
            echo "<input type='submit' name='additem' value=\"" . _sx('button', 'Connect') . "\" class='btn btn-primary'>";
            echo "</td></tr>";
            echo "</table>";
            Html::closeForm();
            echo "</div>";
        }

        echo "<div class='spaced'>";

        if (Simcard::canDelete() && $number) {
            Html::openMassiveActionsForm($container);
            $massiveactionparams = [
                'num_displayed'    => min($_SESSION['glpilist_limit'], $number),
                'specific_actions' => ['purge' => _x('button', 'Disconnect')],
                'container'        => $container,
            ];
            Html::showMassiveActions($massiveactionparams);
        }
        echo "<table class='tab_cadre_fixehov'>";

        $header_begin = "<tr>";
        $header_top = '';
        $header_bottom = '';
        $header_end = '';

        if (Simcard::canDelete()) {
            $header_top .= "<th width='10'>" . Html::getCheckAllAsCheckbox($container);
            $header_top .= "</th>";
            $header_bottom .= "<th width='10'>" . Html::getCheckAllAsCheckbox($container);
            $header_bottom .= "</th>";
        }

        $header_end .= "<th>" . __s('Entity') . "</th>";
        $header_end .= "<th>" . __s('Name') . "</th>";
        $header_end .= "<th>" . __s('IMSI', 'simcard') . "</th>";
        $header_end .= "<th>" . __s('Inventory number') . "</th>";
        $header_end .= "</tr>";

        echo $header_begin . $header_top . $header_end;

        foreach ($results as $data) {
            $tmp = new Simcard();
            $tmp->getFromDB($data['plugin_simcard_simcards_id']);
            echo "<tr class='tab_bg_1'>";
            if (Simcard::canDelete()) {
                echo "<td width='10'>";
                Html::showMassiveActionCheckBox(self::class, $data['id']);
                echo "</td>";
            }
            echo "<td>";
            echo htmlescape(Dropdown::getDropdownName('glpi_entities', $tmp->fields['entities_id']));
            echo "</td>";
            echo "<td>";
            echo $tmp->getLink();
            echo "</td>";
            echo "<td>";
            echo htmlescape($tmp->fields['serial']);
            echo "</td>";
            echo "<td>";
            echo htmlescape($tmp->fields['otherserial']);
            echo "</td>";
            echo "</tr>";
        }
        echo $header_begin . $header_bottom . $header_end;
        echo "</table>";
        if (!empty($results) && isset($massiveactionparams)) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
        }
        echo "</div>";
    }

    public static function getIcon()
    {
        return 'ti ti-sitemap';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (Simcard::canView()) {
            $nb = 0;
            if ($item instanceof Simcard) {
                if ($_SESSION['glpishow_count_on_tabs']) {
                    $nb = self::countForSimcard($item);
                }
                return self::createTabEntry(_n('Associated item', 'Associated items', 2), $nb, $item::getType());
            }
            if ($_SESSION['glpishow_count_on_tabs'] && $item instanceof CommonDBTM) {
                $nb = self::countForItemByItemtype($item);
            }
            return self::createTabEntry(Simcard::getTypeName(2), $nb, $item::getType(), Simcard::getIcon());
        }
        return '';
    }

    /**
     * Count the number of associated items for a simcard
     *
     * @param Simcard $item
     **/
    public static function countForSimcard(Simcard $item): int
    {
        return countElementsInTable(self::getTable(), ['plugin_simcard_simcards_id' => $item->getID()]);
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Simcard) {
            self::showForSimcard($item);
        } elseif ($item instanceof CommonDBTM && in_array($item::class, self::getClasses(), true)) {
            self::showForItem($item);
        }
        return true;
    }
}

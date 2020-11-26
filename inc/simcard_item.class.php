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

// Relation between Simcard and Items (computer, phone, peripheral only)
class PluginSimcardSimcard_Item extends CommonDBRelation
{

    // From CommonDBRelation
    static public $itemtype_1 = 'PluginSimcardSimcard';
    static public $items_id_1 = 'plugin_simcard_simcards_id';

    static public $itemtype_2 = 'itemtype';
    static public $items_id_2 = 'items_id';

    // Itemtypes simcards may be linked to
    static protected $linkableClasses = array(
        'Computer',
        'Peripheral',
        'Phone',
        'Printer',
        'NetworkEquipment'
    );

    /**
     * Name of the type
     *
     * @param $nb  integer  number of item in the type (default 0)
     **/
    static function getTypeName($nb = 0)
    {
        global $LANG;
        return __s('Direct Connections');
    }

    static function countForItem(CommonDBTM $item)
    {
        return countElementsInTable(getTableForItemType(__CLASS__), ["WHERE" => ["plugin_simcard_simcards_id" => $item->getField('id')]]);
    }

    /**
     * Count the number of relations having the itemtype of $item
     *
     * @param CommonDBTM $item Item whose relations to simcards shall be counted
     * @return integer count of relations between the item and simcards
     */
    static function countForItemByItemtype(CommonDBTM $item)
    {
        $id = $item->getField('id');
        $itemtype = $item->getType();
        return countElementsInTable(getTableForItemType(__CLASS__),
            ["WHERE" => ["items_id" => $id, "itemtype" => $itemtype]]);
    }

    /**
     * Hook called After an item is uninstall or purge
     */
    static function cleanForItem(CommonDBTM $item)
    {
        $temp = new self();
        $temp->deleteByCriteria(
            array('itemtype' => $item->getType(),
                'items_id' => $item->getField('id')));
    }

    static function getClasses()
    {
        return self::$linkableClasses;
    }

    /**
     * Declare a new itemtype to be linkable to a simcard
     */
    static function registerItemtype($itemtype)
    {
        if (!in_array($itemtype, self::$linkableClasses)) {
            array_push(self::$linkableClasses, $itemtype);
            Plugin::registerClass('PluginSimcardSimcard_Item',
                array('addtabon' => $itemtype));
        }
    }

    static function install(Migration $migration)
    {
        global $DB;
        $table = getTableForItemType(__CLASS__);
        if (!$DB->TableExists($table)) {
            $query = "CREATE TABLE IF NOT EXISTS `$table` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `items_id` int(11) NOT NULL DEFAULT '0' COMMENT 'RELATION to various table, according to itemtype (id)',
              `plugin_simcard_simcards_id` int(11) NOT NULL DEFAULT '0',
              `itemtype` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
              PRIMARY KEY (`id`),
              KEY `plugin_simcard_simcards_id` (`plugin_simcard_simcards_id`),
              KEY `item` (`itemtype`,`items_id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
            $DB->query($query) or die ($DB->error());
        }
    }

    /**
     *
     *
     * @since 1.3
     **/
    static function upgrade(Migration $migration)
    {
        global $DB;
        $table = getTableForItemType(__CLASS__);
        $DB->query("ALTER TABLE `$table` ENGINE=InnoDB");
    }

    static function uninstall()
    {
        global $DB;
        $table = getTableForItemType(__CLASS__);
        $DB->query("DROP TABLE IF EXISTS `$table`");
    }

    static function showForSimcard(PluginSimcardSimcard $simcard)
    {
        global $DB, $LANG;

        if (!$simcard->canView()) {
            return;
        }

//       DEPRECATED SINCE 9.5
//      $results = getAllDatasFromTable(getTableForItemType(__CLASS__),
//                                     "`plugin_simcard_simcards_id` = '".$simcard->getID()."'");

        $results = getAllDataFromTable(getTableForItemType(__CLASS__), ['plugin_simcard_simcards_id' => $simcard->getID()]);
        $number = count($results);
        $rand = mt_rand();

        if (PluginSimcardsimcard::canUpdate() && !$number) {

            echo "<div class='firstbloc'>";
            echo "<form id='items' name='items' method='post' action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_2'><th colspan='2'>" . __('Connect a Device') . "</th></tr>";
            echo "<tr class='tab_bg_1'><td>";
            echo "<input type='hidden' name='plugin_simcard_simcards_id' value='" . $simcard->getID() . "'>";
            Dropdown::showSelectItemFromItemtypes(['itemtypes' => self::getClasses(),
                'entity_restrict' => $simcard->fields['entities_id']]);
            echo "</td><td class='center'>";
            echo "<input type='submit' name='additem' value=\"" . _sx('button', 'Connect') . "\" class='submit'>";
            echo "</td></tr>";
            echo "</table>";
            Html::closeForm();
            echo "</div>";
        }

        echo "<div class='spaced'>";
        if (PluginSimcardsimcard::canUpdate() && $number) {
            Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
            $massiveactionparams
                = ['num_displayed'
            => min($_SESSION['glpilist_limit'], $number),
                'specific_actions'
                => ['purge' => _x('button', 'Disconnect')],
                'container'
                => 'mass' . __CLASS__ . $rand];
            Html::showMassiveActions($massiveactionparams);
        }
        echo "<table class='tab_cadre_fixehov'>";
        if (!empty($results)) {

            $header_begin = "<tr>";
            $header_top = '';
            $header_bottom = '';
            $header_end = '';

            if (PluginSimcardsimcard::canUpdate()) {
                $header_top .= "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
                $header_top .= "</th>";
                $header_bottom .= "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
                $header_bottom .= "</th>";
            }

            $header_end .= "<th>" . __s('Type') . "</th>";
            $header_end .= "<th>" . __s("Entity") . "</th>";
            $header_end .= "<th>" . __s("Name") . "</th>";
            $header_end .= "<th>" . __('Serial number') . "</th>";
            $header_end .= "<th>" . __('Inventory number') . "</th>";
            $header_end .= "</tr>";
            echo $header_begin . $header_top . $header_end;

            foreach ($results as $data) {
                $item = new $data['itemtype'];
                $item->getFromDB($data['items_id']);
                echo "<tr class='tab_bg_1'>";

                if (PluginSimcardsimcard::canUpdate()) {
                    echo "<td width='10'>";
                    Html::showMassiveActionCheckBox(__CLASS__, $data["id"]);
                    echo "</td>";
                }

                echo "<td>";
                echo call_user_func(array($data['itemtype'], 'getTypeName'));
                echo "</td>";
                echo "<td>";
                echo Dropdown::getDropdownName('glpi_entities', $item->fields['entities_id']);
                echo "</td>";
                echo "<td>";
                echo $item->getLink();
                echo "</td>";
                echo "<td>";
                if (isset($item->fields['serial'])) {
                    echo $item->fields['serial'];
                }
                echo "</td>";
                echo "<td>";
                if (isset($item->fields['otherserial'])) {
                    echo $item->fields['otherserial'];
                }
                echo "</td>";
                echo "</tr>";
            }
            echo $header_begin . $header_bottom . $header_end;
        } else {
            echo "<tr><td class='tab_bg_1 b'><i>" . __('Not connected') . "</i>";
            echo "</td></tr>";
        }
        echo "</table>";
        if (PluginSimcardsimcard::canUpdate() && $number) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();
        }

        echo "</div>";

    }

    static function showForItem(CommonDBTM $item)
    {
        global $DB, $LANG;

        if (!$item->canView()) {
            return;
        }

//      $results = getAllDatasFromTable(getTableForItemType(__CLASS__),
//                                     "`items_id` = '".$item->getID()."' AND `itemtype`='".get_class($item)."'");

        $results = getAllDataFromTable(getTableForItemType(__CLASS__), ['items_id' => $item->getID(), 'itemtype' => get_class($item)]);
        $rand = mt_rand();


        $number = count($results);
        if (PluginSimcardSimcard::canUpdate()) {
            echo "<div class='firstbloc'>";
            echo "<form id='items' name='items' method='post' action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_2'><th colspan='2'>" . __s('Connect SIM cards') . "</th></tr>";
            echo "<tr class='tab_bg_1'><td>";
            echo "<input type='hidden' name='items_id' value='" . $item->getID() . "'>";
            echo "<input type='hidden' name='itemtype' value='" . $item->getType() . "'>";
            $used = array();
            $query = "SELECT `id`
                   FROM `glpi_plugin_simcard_simcards`
                   WHERE `is_template`='0'
                      AND `id` IN (SELECT `plugin_simcard_simcards_id`
                                   FROM `glpi_plugin_simcard_simcards_items`)";
            foreach ($DB->request($query) as $use) {
                $used[] = $use['id'];
            }
            Dropdown::show('PluginSimcardSimcard',
                array('name' => "plugin_simcard_simcards_id",
                    'entity' => $item->fields['entities_id'], 'used' => $used));
            echo "</td>";
            echo "</td><td class='center' width='20%'>";
            echo "<input type='submit' name='additem' value=\"" . _sx('button', 'Connect') . "\" class='submit'>";
            echo "</td></tr>";
            echo "</table>";
            Html::closeForm();
            echo "</div>";
        }

        echo "<div class='spaced'>";

        if (PluginSimcardSimcard::canDelete() && $number) {
            Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
            $massiveactionparams
                = ['num_displayed'
            => min($_SESSION['glpilist_limit'], $number),
                'specific_actions'
                => ['purge' => _x('button', 'Disconnect')],
                'container'
                => 'mass' . __CLASS__ . $rand];
            Html::showMassiveActions($massiveactionparams);
        }
        echo "<table class='tab_cadre_fixehov'>";

        $header_begin = "<tr>";
        $header_top = '';
        $header_bottom = '';
        $header_end = '';

        if (PluginSimcardSimcard::canDelete()) {

            $header_top .= "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
            $header_top .= "</th>";
            $header_bottom .= "<th width='10'>" . Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
            $header_bottom .= "</th>";
        }

        $header_end .= "<th>" . __s('Entity') . "</th>";
        $header_end .= "<th>" . __s('Name') . "</th>";
        $header_end .= "<th>" . __s('IMSI') . "</th>";
        $header_end .= "<th>" . __s('Inventory number') . "</th>";
        $header_end .= "</tr>";

        echo $header_begin . $header_top . $header_end;

        foreach ($results as $data) {
            $tmp = new PluginSimcardSimcard();
            $tmp->getFromDB($data['plugin_simcard_simcards_id']);
            echo "<tr class='tab_bg_1'>";
            if (PluginSimcardSimcard::canDelete()) {
//               echo "<input type='checkbox' name='todelete[".$data['id']."]'>";
                echo "<td width='10'>";
                Html::showMassiveActionCheckBox(__CLASS__, $data["id"]);
                echo "</td>";
            }
            echo "<td>";
            echo Dropdown::getDropdownName('glpi_entities', $tmp->fields['entities_id']);
            echo "</td>";
            echo "<td>";
            echo $tmp->getLink();
            echo "</td>";
            echo "<td>";
            echo $tmp->fields['serial'];
            echo "</td>";
            echo "<td>";
            echo $tmp->fields['otherserial'];
            echo "</td>";
            echo "</tr>";
        }
        echo $header_begin . $header_bottom . $header_end;
        echo "</table>";
        if (!empty($results)) {
//            Html::openArrowMassives('items', true);
//            Html::closeArrowMassives(array ('delete_items' => _sx('button', 'Disconnect')));
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
        }
        echo "</div>";
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        global $CFG_GLPI;

        if (PluginSimcardSimcard::canView()) {
            switch ($item->getType()) {
                case 'PluginSimcardSimcard' :
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        return self::createTabEntry(_n('Associated item', 'Associated items', 2), self::countForSimcard($item));
                    }
                    return _n('Associated item', 'Associated items', 2);

                default :
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        return self::createTabEntry(PluginSimcardSimcard::getTypeName(2), self::countForItemByItemtype($item));
                    }
                    return _n('SIM card', 'SIM cards', 2);
            }
        }
        return '';
    }


    /**
     *
     * Count the number of associated items for a simcard item
     *
     * @param $item   Simcard object
     **/
    static function countForSimcard(PluginSimcardSimcard $item)
    {

        $restrict = ["WHERE" => ["plugin_simcard_simcards_id" => $item->getField('id')]];
        return countElementsInTable(array('glpi_plugin_simcard_simcards_items'), $restrict);
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if (in_array(get_class($item), PluginSimcardSimcard_Item::getClasses())) {
            self::showForItem($item);
        } elseif (get_class($item) == 'PluginSimcardSimcard') {
            self::showForSimcard($item);
        }
        return true;
    }
}

?>

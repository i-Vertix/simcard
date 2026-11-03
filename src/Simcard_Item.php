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
use Dropdown;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Plugin;

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

    public function prepareInputForAdd($input)
    {
        if (
            !is_array($input)
            || !isset($input['itemtype'])
            || !in_array($input['itemtype'], self::getClasses(), true)
        ) {
            return false;
        }
        return parent::prepareInputForAdd($input);
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

    public static function showForSimcard(Simcard $simcard): void
    {
        global $DB;

        if (!$simcard->canView()) {
            return;
        }

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['plugin_simcard_simcards_id' => $simcard->getID()],
        ]);

        $entries = [];
        foreach ($iterator as $data) {
            if (!in_array($data['itemtype'], self::getClasses(), true)) {
                continue;
            }
            $linked = getItemForItemtype($data['itemtype']);
            if (!$linked instanceof CommonDBTM || !$linked->getFromDB($data['items_id'])) {
                continue;
            }
            $entries[] = [
                'itemtype'    => self::class,
                'id'          => $data['id'],
                'type'        => $linked::getTypeName(1),
                'entity'      => Dropdown::getDropdownName('glpi_entities', $linked->fields['entities_id']),
                'name'        => self::getLinkedItemName($linked),
                'serial'      => $linked->fields['serial'] ?? '',
                'otherserial' => $linked->fields['otherserial'] ?? '',
            ];
        }

        $can_edit = $simcard->can($simcard->getID(), UPDATE);
        $columns = [
            'type'        => _n('Type', 'Types', 1),
            'entity'      => Entity::getTypeName(1),
            'name'        => __('Name'),
            'serial'      => __('Serial number'),
            'otherserial' => __('Inventory number'),
        ];

        self::renderTab('simcard', $can_edit, $entries, $columns, [
            'simcard'         => $simcard,
            'entity_restrict' => (int) $simcard->fields['entities_id'],
            'used_simcards'   => [],
        ]);
    }

    public static function showForItem(CommonDBTM $item): void
    {
        global $DB;

        if (!$item->canView()) {
            return;
        }

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'items_id' => $item->getID(),
                'itemtype' => $item::class,
            ],
        ]);

        $entries = [];
        foreach ($iterator as $data) {
            $simcard = new Simcard();
            if (!$simcard->getFromDB($data['plugin_simcard_simcards_id'])) {
                continue;
            }
            $entries[] = [
                'itemtype'    => self::class,
                'id'          => $data['id'],
                'entity'      => Dropdown::getDropdownName('glpi_entities', $simcard->fields['entities_id']),
                'name'        => self::getLinkedItemName($simcard),
                'serial'      => $simcard->fields['serial'],
                'otherserial' => $simcard->fields['otherserial'],
            ];
        }

        // A SIM card can only be linked once: hide the already linked ones
        $used = [];
        foreach ($DB->request([
            'SELECT'   => 'plugin_simcard_simcards_id',
            'DISTINCT' => true,
            'FROM'     => self::getTable(),
        ]) as $row) {
            $used[] = (int) $row['plugin_simcard_simcards_id'];
        }

        $can_edit = Simcard::canUpdate() || $item->can($item->getID(), UPDATE);
        $columns = [
            'entity'      => Entity::getTypeName(1),
            'name'        => __('Name'),
            'serial'      => __('IMSI', 'simcard'),
            'otherserial' => __('Inventory number'),
        ];

        self::renderTab('item', $can_edit, $entries, $columns, [
            'item'            => $item,
            'entity_restrict' => (int) $item->fields['entities_id'],
            'used_simcards'   => $used,
        ]);
    }

    /**
     * Name of a linked element as raw HTML: a link if the user may view it, escaped plain text otherwise.
     */
    private static function getLinkedItemName(CommonDBTM $linked): string
    {
        if ($linked->canView()) {
            return $linked->getLink();
        }
        return htmlescape($linked->getName());
    }

    private static function renderTab(string $mode, bool $can_edit, array $entries, array $columns, array $params): void
    {
        TemplateRenderer::getInstance()->display('@simcard/simcard_item.html.twig', $params + [
            'form_mode' => $mode,
            'can_edit'  => $can_edit,
            'form_url'  => self::getFormURL(),
            'classes'   => self::getClasses(),
            'simcard_itemtype' => Simcard::class,
            'datatable' => [
                'is_tab'              => true,
                'nofilter'            => true,
                'nosort'              => true,
                'columns'             => $columns,
                'formatters'          => ['name' => 'raw_html'],
                'entries'             => $entries,
                'total_number'        => count($entries),
                'showmassiveactions'  => $can_edit,
                'massiveactionparams' => [
                    'num_displayed'    => count($entries),
                    'itemtype'         => self::class,
                    'specific_actions' => ['purge' => _x('button', 'Disconnect')],
                    'container'        => 'massSimcardItem' . mt_rand(),
                ],
            ],
        ]);
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

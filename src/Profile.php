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

use CommonGLPI;
use ProfileRight;

class Profile extends \Profile
{
    public const RIGHT_SIMCARD_SIMCARD = 'simcard:simcard';
    public const SIMCARD_ASSOCIATE_TICKET = 128;

    public static string $rightname = 'profile';

    public function createAccess($ID)
    {
        $this->add(['profiles_id' => $ID]);
    }

    /**
     * Grant a profile full access to SIM cards (and ticket association), and make
     * SIM cards selectable on helpdesk items of that profile.
     */
    public static function createFirstAccess(int $profiles_id): void
    {
        global $DB;

        $currentRights = ProfileRight::getProfileRights(
            $profiles_id,
            [self::RIGHT_SIMCARD_SIMCARD]
        );
        $firstAccessRights = array_merge($currentRights, [
            self::RIGHT_SIMCARD_SIMCARD => ALLSTANDARDRIGHT
                + self::SIMCARD_ASSOCIATE_TICKET
                + READNOTE
                + UPDATENOTE,
        ]);
        ProfileRight::updateProfileRights($profiles_id, $firstAccessRights);

        // Persist the helpdesk item type (the profile right hook is not active during installation)
        $profile = new \Profile();
        if ($profile->getFromDB($profiles_id)) {
            $types = $profile->fields['helpdesk_item_type'] ?? [];
            if (!is_array($types)) {
                $types = json_decode((string) $types, true);
            }
            $types = is_array($types) ? $types : [];
            if (!in_array(Simcard::class, $types, true)) {
                $types[] = Simcard::class;
                $DB->update(
                    \Profile::getTable(),
                    ['helpdesk_item_type' => json_encode(array_values($types))],
                    ['id' => $profiles_id]
                );
            }
        }

        // Update the current session only if it belongs to this profile
        if (($_SESSION['glpiactiveprofile']['id'] ?? null) == $profiles_id) {
            $_SESSION['glpiactiveprofile'][self::RIGHT_SIMCARD_SIMCARD] = $firstAccessRights[self::RIGHT_SIMCARD_SIMCARD];
            if (!in_array(Simcard::class, $_SESSION['glpiactiveprofile']['helpdesk_item_type'] ?? [], true)) {
                $_SESSION['glpiactiveprofile']['helpdesk_item_type'][] = Simcard::class;
            }
        }
    }

    /**
     * Print the profile form
     *
     * @param int   $ID      id of the profile
     * @param array $options
     *
     * @return bool
     **/
    public function showForm($ID, array $options = [])
    {
        if (!\Profile::canView()) {
            return false;
        }
        $canedit = self::canUpdate();
        $profile = new \Profile();
        if ($ID) {
            $profile->getFromDB($ID);
        }
        if ($canedit) {
            echo "<form action='" . htmlescape($profile->getFormURL()) . "' method='post'>";
        }

        $profile->displayRightsChoiceMatrix(self::getAllRights(), [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
        ]);

        if ($canedit) {
            echo "<div class='center'>";
            echo "<input type='hidden' name='id' value='" . (int) $ID . "'>";
            echo "<input type='submit' name='update' value=\"" . _sx('button', 'Save') . "\" class='btn btn-primary'>";
            echo "</div>";
        }
        \Html::closeForm();
        $this->showLegend();
        return true;
    }

    public static function translateARight($old_right)
    {
        switch ($old_right) {
            case 'r':
                return READ;

            case 'w':
                return ALLSTANDARDRIGHT;

            case '1':
                return self::SIMCARD_ASSOCIATE_TICKET;

            case '0':
            case '':
            default:
                return 0;
        }
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof \Profile) {
            return self::createTabEntry(_n('SIM card', 'SIM cards', 2, 'simcard'));
        }
        return '';
    }

    public static function getIcon()
    {
        return Simcard::getIcon();
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof \Profile) {
            $profile = new self();
            $profile->showForm($item->getField('id'));
        }
        return true;
    }

    public static function getAllRights(): array
    {
        return [
            [
                'itemtype' => Simcard::class,
                'label'    => Simcard::getTypeName(2),
                'field'    => self::RIGHT_SIMCARD_SIMCARD,
            ],
        ];
    }
}

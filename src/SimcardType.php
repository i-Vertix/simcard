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

use CommonDropdown;

/// Class SimcardType
class SimcardType extends CommonDropdown
{
    public static function getTypeName($nb = 0)
    {
        return __('Type of SIM card', 'simcard');
    }
/**
     * Find or create the equivalent of dropdown value $ID in entity $entity.
     *
     * @return int ID of the value in the target entity, 0 if the source value does not exist
     */
    public static function transfer(int $ID, int $entity): int
    {
        if ($ID <= 0) {
            return 0;
        }

        $source = new self();
        if (!$source->getFromDB($ID)) {
            return 0;
        }

        $target = new self();
        return (int) $target->import([
            'name'        => $source->fields['name'],
            'comment'     => $source->fields['comment'] ?? '',
            'entities_id' => $entity,
        ]);
    }
}

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

use Glpi\Event;

include('../../../inc/includes.php');

Session::checkCentralAccess();

$simcard_item = new PluginSimcardSimcard_Item();
if (isset($_POST["additem"])) {
    if (isset($_POST["items_id"]) && ($_POST["items_id"] > 0)) {
        $simcard_item->check(-1, CREATE, $_POST);
        if ($simcard_item->add($_POST)) {
            Event::log($_POST["plugin_simcard_simcards_id"], "simcards", 5, "inventory",
                //TRANS: %s is the user login
                sprintf(__('%s connects an item'), $_SESSION["glpiname"]));
        }
    }
    Html::back();
} else if (isset($_POST["delete_items"])) {
    if (isset($_POST['todelete'])) {
        foreach ($_POST['todelete'] as $id => $val) {
            if ($val == 'on') {
                $simcard_item->check($id, PURGE);
                $simcard_item->delete(array('id' => $id), 1);
            }
        }
    }
    Html::back();
}
Html::displayErrorAndDie('Lost');

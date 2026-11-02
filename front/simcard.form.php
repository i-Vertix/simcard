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
use GlpiPlugin\Simcard\Simcard;

$simcard = new Simcard();

if (isset($_POST['add'])) {
    // Add a new simcard
    $simcard->check(-1, CREATE, $_POST);
    if ($newID = $simcard->add($_POST)) {
        Event::log(
            $newID,
            'simcard',
            4,
            'inventory',
            sprintf(__('%1$s adds the item %2$s'), $_SESSION['glpiname'], $_POST['name'])
        );
    }
    Html::back();
} elseif (isset($_POST['delete'])) {
    // Delete a simcard
    $simcard->check($_POST['id'], DELETE);
    if ($simcard->delete($_POST)) {
        Event::log(
            $_POST['id'],
            'simcard',
            4,
            'inventory',
            //TRANS: %s is the user login
            sprintf(__('%s deletes an item'), $_SESSION['glpiname'])
        );
    }
    $simcard->redirectToList();
} elseif (isset($_POST['restore'])) {
    $simcard->check($_POST['id'], PURGE);
    if ($simcard->restore($_POST)) {
        Event::log(
            $_POST['id'],
            'simcard',
            4,
            'inventory',
            //TRANS: %s is the user login
            sprintf(__('%s restores the item'), $_SESSION['glpiname'])
        );
    }
    $simcard->redirectToList();
} elseif (isset($_POST['purge'])) {
    $simcard->check($_POST['id'], PURGE);
    if ($simcard->delete($_POST, true)) {
        Event::log(
            $_POST['id'],
            'simcard',
            4,
            'inventory',
            //TRANS: %s is the user login
            sprintf(__('%s purges an item'), $_SESSION['glpiname'])
        );
    }
    $simcard->redirectToList();
} elseif (isset($_POST['update'])) {
    // Update a simcard
    $simcard->check($_POST['id'], UPDATE);
    $simcard->update($_POST);
    Event::log(
        $_POST['id'],
        'simcard',
        4,
        'inventory',
        //TRANS: %s is the user login
        sprintf(__('%s updates an item'), $_SESSION['glpiname'])
    );
    Html::back();
} else {
    // Print simcard information
    Simcard::displayFullPageForItem(
        $_GET['id'] ?? 0,
        Simcard::getSectorizedDetails(),
        ['withtemplate' => $_GET['withtemplate'] ?? '']
    );
}

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

/// class SimcardInjection
class PluginSimcardSimcardInjection extends PluginSimcardSimcard implements PluginDatainjectionInjectionInterface
{

    public function __construct()
    {
        parent::__construct();
        //Needed for getSearchOptions !
        $this->table = getTableForItemType(get_parent_class($this));
    }


    public function isPrimaryType()
    {
        return true;
    }


    public function connectedTo()
    {
        return array();
    }


    /**
     * Return the table used to store this object
     *
     * @param string $classname Force class (to avoid late_binding on inheritance)
     *
     * @return string
     **/
    public static function getTable($classname = null): string
    {
        $parenttype = get_parent_class(__CLASS__);
        return $parenttype::getTable();
    }

    /**
     * @see plugins/datainjection/inc/PluginDatainjectionInjectionInterface::getOptions()
     **/
    public function getOptions($primary_type = '')
    {
        $tab = Search::getOptions(get_parent_class($this));

        //Remove some options because some fields cannot be imported
        $blacklist = PluginDatainjectionCommonInjectionLib::getBlacklistedOptions(get_parent_class($this));
        $notimportable = [];

        $options['ignore_fields'] = array_merge($blacklist, $notimportable);
        $options['displaytype'] = ["multiline_text" => [16]];

        return PluginDatainjectionCommonInjectionLib::addToSearchOptions($tab, $options, $this);
    }


    /**
     * Standard method to add an object into glpi
     *
     * @param values fields to add into glpi
     * @param options options used during creation
     *
     * @return an array of IDs of newly created objects : for example array(Computer=>1, Networkport=>10)
     */
    public function addOrUpdateObject($values = array(), $options = array())
    {

        $lib = new PluginDatainjectionCommonInjectionLib($this, $values, $options);
        $lib->processAddOrUpdate();
        return $lib->getInjectionResults();
    }
}

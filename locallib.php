<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AMOS local library
 *
 * @package   amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/mlanglib.php');

/**
 * TODO: short description.
 *
 * TODO: long description.
 */
class local_amos {

    /**
     * TODO: short description.
     *
     * @return TODO
     */
    public static function versions() {

        $versions = array();

        $version                    = new stdclass();
        $version->code              = 20;
        $version->label             = '2.0 dev';
        $version->branch            = 'MOODLE_20_STABLE';
        $version->translatable      = true;
        $version->current           = true;
        $version->checked           = null;
        $versions[$version->code]   = $version;

        $version                    = new stdclass();
        $version->code              = 19;
        $version->label             = '1.9';
        $version->branch            = 'MOODLE_19_STABLE';
        $version->translatable      = true;
        $version->current           = true;
        $version->checked           = null;
        $versions[$version->code]   = $version;

        $version                    = new stdclass();
        $version->code              = 18;
        $version->label             = '1.8';
        $version->branch            = 'MOODLE_18_STABLE';
        $version->translatable      = true;
        $version->current           = false;
        $version->checked           = null;
        $versions[$version->code]   = $version;

        $version                    = new stdclass();
        $version->code              = 17;
        $version->label             = '1.7';
        $version->branch            = 'MOODLE_17_STABLE';
        $version->translatable      = true;
        $version->current           = false;
        $version->checked           = null;
        $versions[$version->code]   = $version;

        $version                    = new stdclass();
        $version->code              = 16;
        $version->label             = '1.6';
        $version->branch            = 'MOODLE_16_STABLE';
        $version->translatable      = true;
        $version->current           = false;
        $version->checked           = null;
        $versions[$version->code]   = $version;

        return $versions;
    }
}

/**
 * TODO: short description.
 *
 * TODO: long description.
 */
class local_amos_filter implements renderable {

    /** @var moodle_url */
    protected $handler;

    /** @var array of versions meta info */
    protected $versions;

    /**
     * TODO: short description.
     *
     * @param string $actionhandler URL
     *
     */
    public function __construct(moodle_url $handler) {
        $this->handler  = $handler;
        $this->versions = array();
    }

    /**
     * TODO: short description.
     *
     * @param array $checked the list of version codes
     * @return TODO
     */
    public function set_versions($checked = array()) {

        $this->versions = local_amos::versions();

        if (empty($checked)) {
            // by default, only current versions are chosen
            foreach ($this->versions as $version) {
                $version->checked = $version->current;
            }
        } else {
            // apply what user has checked
            foreach ($checked as $versioncode) {
                if (isset($this->versions[$versioncode])) {
                    $this->versions[$versioncode]->checked = true;
                } else {
                    $this->versions[$versioncode]->checked = false;
                }
            }
        }
    }

    /**
     * Returns the array of all version that were checked by the user
     *
     * This function creates several anonymous functions but the list of versions if reasonable short
     *
     * @see self::set_versions()
     * @return array of stdclass
     */
    public function get_versions() {
        return array_filter($this->versions, create_function('$v', 'return !empty($v->checked);'));
    }
}

/**
 * TODO: short description.
 *
 * TODO: long description.
 */
class local_amos_translator implements renderable {

    /**
     * TODO: short description.
     *
     * @param mixed $filter 
     * @return TODO
     */
    public function load_strings($filter, $orderby='') {
        global $DB;

        $sql = "SELECT *
                  FROM {local_amos_strings}
                 WHERE pack = :pack
                       AND component = :component";
        $params = array('pack' => 'cs', 'component' => 'langconfig');

        // filter by branches
        $branches = array();
        foreach ($filter['fver'] as $version) {
            $branches[] = $version->branch;
        }
        if (!empty($branches)) {
            list($sqlbranches, $paramsbranches) = $DB->get_in_or_equal($branches, SQL_PARAMS_NAMED);
            $sql .= " AND branch {$sqlbranches}";
            $params = array_merge($params, $paramsbranches);
        }

        if (is_array($orderby)) {
            $orderby = implode(',', $orderby);
        }
        if (empty($orderby)) {
            $orderby = 'component,stringid,branch,pack';
        }
        $sql .= " ORDER BY {$orderby}";


            print_object($sql); die(); // DONOTCOMMIT

    }
}

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
 * Represent the AMOS translator filter and its settings
 */
class local_amos_filter implements renderable {

    /** @var moodle_url */
    public $handler;

    /** @var list of Moodle versions to show strings from */
    public $version = array();

    /** @var list of languages to show next to the English master strings */
    public $lang = array();

    /**
     * Creates the filter and sets the default filter values
     *
     * @param moodle_url $handler filter form action URL
     */
    public function __construct(moodle_url $handler) {
        $this->handler  = $handler;
    }

    /**
     * Returns the filter data
     *
     * @return object
     */
    public function get_data() {
        $data = new stdclass();

        $default    = $this->get_data_default();
        $submitted  = $this->get_data_submitted();

        $fields = array('version', 'language', 'component', 'missing', 'substring');
        foreach ($fields as $field) {
            if (isset($submitted->{$field})) {
                $data->{$field} = $submitted->{$field};
            } else {
                $data->{$field} = $default->{$field};
            }
        }

        return $data;
    }

    /**
     * Returns the default values of the filter fields
     *
     * @return object
     */
    protected function get_data_default() {
        $data = new stdclass();

        $data->version = array();
        foreach (mlang_version::list_translatable() as $version) {
            if ($version->current) {
                $data->version[] = $version->code;
            }
        }
        $data->language = array('cs');
        $data->component = array();
        $data->missing = false;
        $data->substring = '';

        return $data;
    }

    /**
     * Returns the form data as submitted by the user
     *
     * @return object
     */
    protected function get_data_submitted() {
        $data = new stdclass();

        $fver = optional_param('fver', null, PARAM_INT);
        if (!is_null($fver)) {
            $data->version = array();
            foreach (mlang_version::list_translatable() as $version) {
                if (in_array($version->code, $fver)) {
                    $data->version[] = $version->code;
                }
            }
        }

        $flng = optional_param('flng', null, PARAM_SAFEDIR);
        if (!is_null($flng)) {
            $data->language = array();
            foreach ($flng as $language) {
                // todo if valid language code
                $data->language[] = $language;
            }
        }

        $fcmp = optional_param('fcmp', null, PARAM_SAFEDIR);
        if (!is_null($fcmp)) {
            $data->component = array();
            foreach ($fcmp as $component) {
                // todo if valid component
                $data->component[] = $component;
            }
        }

        $data->missing = optional_param('fmis', false, PARAM_BOOL);

        $data->substring = optional_param('ftxt', '', PARAM_RAW);

        return $data;
    }
}

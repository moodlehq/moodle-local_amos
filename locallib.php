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

    /** @var array list of setting names */
    public $fields = array();

    /** @var moodle_url */
    public $handler;

    /** @var string lazyform name */
    public $lazyformname;

    /**
     * Creates the filter and sets the default filter values
     *
     * @param moodle_url $handler filter form action URL
     */
    public function __construct(moodle_url $handler) {
        $this->fields = array('target','version', 'language', 'component', 'missing', 'substring');
        $this->lazyformname = 'amosfilter';
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

        foreach ($this->fields as $field) {
            if (isset($submitted->{$field})) {
                $data->{$field} = $submitted->{$field};
            } else {
                $data->{$field} = $default->{$field};
            }
        }

        // if the user did not check any version, use the default instead of none
        if (empty($data->version)) {
            foreach (mlang_version::list_translatable() as $version) {
                if ($version->current) {
                    $data->version[] = $version->code;
                }
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

        // if we have a previously saved filter settings in the session, use it
        foreach ($this->fields as $field) {
            $data->{$field} = unserialize(get_user_preferences('amos_' . $field));
        }

        if (is_null($data->target)) {
            $data->target = current_language();
        }
        if (empty($data->version)) {
            foreach (mlang_version::list_translatable() as $version) {
                if ($version->current) {
                    $data->version[] = $version->code;
                }
            }
        }
        if (is_null($data->language)) {
            $data->language = array('en');
        }
        if (is_null($data->component)) {
           $data->component = array();
        }
        if (is_null($data->missing)) {
           $data->missing = false;
        }
        if (is_null($data->substring)) {
            $data->substring = '';
        }

        return $data;
    }

    /**
     * Returns the form data as submitted by the user
     *
     * @return object
     */
    protected function get_data_submitted() {
        $issubmitted = optional_param('__lazyform_' . $this->lazyformname, null, PARAM_BOOL);
        if (empty($issubmitted)) {
            return null;
        }
        require_sesskey();
        $data = new stdclass();

        // todo if valid language code
        $data->target = optional_param('ftgt', null, PARAM_SAFEDIR);

        $data->version = array();
        $fver = optional_param('fver', null, PARAM_INT);
        if (!is_null($fver)) {
            foreach (mlang_version::list_translatable() as $version) {
                if (in_array($version->code, $fver)) {
                    $data->version[] = $version->code;
                }
            }
        }

        $data->language = array();
        $flng = optional_param('flng', null, PARAM_SAFEDIR);
        if (!is_null($flng)) {
            foreach ($flng as $language) {
                // todo if valid language code
                if ($language != $data->target) {
                    $data->language[] = $language;
                }
            }
        }

        $data->component = array();
        $fcmp = optional_param('fcmp', null, PARAM_SAFEDIR);
        if (!is_null($fcmp)) {
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

/**
 * Represents the translation tool
 */
class local_amos_translator implements renderable {

    /** @var array of stdclass strings to display */
    public $strings = array();

    /* @var mlang_persistent_stage */
    protected $stage = null;

    /**
     * Given AMOS string id, returns the suitable name of HTTP parameter to hold the translation
     *
     * @see self::decode_identifier()
     * @param int $amosid_original AMOS id of the English origin of the string
     * @param int $amosid_translation AMOS id of the string translation, if it exists
     * @return string to be safely used as a name of the textarea or HTTP parameter
     */
    public static function encode_identifier($amosid_original, $amosid_translation=null) {
        if (empty($amosid_original) && ($amosid_original !== 0)) {
            throw new coding_exception('Illegal AMOS string identifier passed');
        }
        return 's_' . $amosid_original . '_' . $amosid_translation;
    }

    /**
     * @param local_amos_filter $filter
     * @param stdclass $user working with the translator
     * @param moodle_url $handler processing the submitted translation
     */
    public function __construct(local_amos_filter $filter, stdclass $user, moodle_url $handler) {
        $this->handler = $handler;
        $this->stage = mlang_persistent_stage::instance_for_user($user);

        // get the list of strings to display according the current filter values
        $string = new stdclass();
        $string->branch = '2.0';
        $string->language = 'cs';
        $string->component = 'moodle';
        $string->stringid = 'editthis';
        $string->params = array('$a activity type');
        $string->original = 'Edit this {$a}';
        $string->originalid = 23;
        $string->translation = 'Upravit tuto činnost: {$a}';
        $string->translationid = 786;
        $this->strings[] = $string;

        $string = new stdclass();
        $string->branch = '2.0';
        $string->language = 'cs';
        $string->component = 'moodle';
        $string->stringid = 'view';
        $string->params = array();
        $string->original = <<<EOF
<h1>View</h1>

this
is
quite a long string. this is quite a long string. this is quite a long string. this is quite a long string. this is quite a long string. this is quite a long string. this is quite a long string
EOF;
        $string->originalid = 24;
        $string->translation = null;
        $string->translationid = null;
        $this->strings[] = $string;

    }
}

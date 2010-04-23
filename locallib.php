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
        $this->fields = array('version', 'language', 'component', 'missing', 'helps', 'substring');
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
        global $USER;

        $data = new stdclass();

        // if we have a previously saved filter settings in the session, use it
        foreach ($this->fields as $field) {
            if (isset($USER->{'local_amos_' . $field})) {
                $data->{$field} = unserialize($USER->{'local_amos_' . $field});
            } else {
                $data->{$field} = null;
            }
        }

        if (empty($data->version)) {
            foreach (mlang_version::list_translatable() as $version) {
                if ($version->current) {
                    $data->version[] = $version->code;
                }
            }
        }
        if (is_null($data->language)) {
            $data->language = array(current_language());
        }
        if (is_null($data->component)) {
           $data->component = array();
        }
        if (is_null($data->missing)) {
           $data->missing = false;
        }
        if (is_null($data->helps)) {
           $data->helps = false;
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
                $data->language[] = $language;
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

        $data->missing      = optional_param('fmis', false, PARAM_BOOL);
        $data->helps        = optional_param('fhlp', false, PARAM_BOOL);
        $data->substring    = trim(optional_param('ftxt', '', PARAM_RAW));

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
     * @param local_amos_filter $filter
     * @param stdclass $user working with the translator
     * @param moodle_url $handler processing the submitted translation
     */
    public function __construct(local_amos_filter $filter, stdclass $user, moodle_url $handler) {
        global $DB;

        $this->handler = $handler;
        $this->stage = mlang_persistent_stage::instance_for_user($user);

        // get the list of strings to display according the current filter values
        $branches = $filter->get_data()->version;
        $languages = array_merge(array('en'), $filter->get_data()->language);
        $components = $filter->get_data()->component;
        if (empty($branches) or empty($components) or empty($languages)) {
            return;
        }
        $missing = $filter->get_data()->missing;
        $helps = $filter->get_data()->helps;
        $substring = $filter->get_data()->substring;
        list($inner_sqlbranches, $inner_paramsbranches) = $DB->get_in_or_equal($branches, SQL_PARAMS_NAMED, 'innerbranch00000');
        list($inner_sqllanguages, $inner_paramslanguages) = $DB->get_in_or_equal($languages, SQL_PARAMS_NAMED, 'innerlang00000');
        list($inner_sqlcomponents, $inner_paramcomponents) = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED, 'innercomp00000');
        list($outer_sqlbranches, $outer_paramsbranches) = $DB->get_in_or_equal($branches, SQL_PARAMS_NAMED, 'outerbranch00000');
        list($outer_sqllanguages, $outer_paramslanguages) = $DB->get_in_or_equal($languages, SQL_PARAMS_NAMED, 'outerlang00000');
        list($outer_sqlcomponents, $outer_paramcomponents) = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED, 'outercomp00000');
        $sql = "SELECT r.id, r.branch, r.lang, r.component, r.stringid, r.text, r.timemodified
                  FROM {amos_repository} r
                  JOIN (SELECT branch, lang, component, stringid, MAX(timemodified) AS timemodified
                          FROM {amos_repository}
                         WHERE branch $inner_sqlbranches
                           AND lang $inner_sqllanguages
                           AND component $inner_sqlcomponents";
        $sql .= "     GROUP BY branch,lang,component,stringid) j
                    ON (r.branch = j.branch
                       AND r.lang = j.lang
                       AND r.component = j.component
                       AND r.stringid = j.stringid
                       AND r.timemodified = j.timemodified)
                 WHERE r.branch {$outer_sqlbranches}
                       AND r.lang {$outer_sqllanguages}
                       AND r.component {$outer_sqlcomponents}
                       AND r.deleted = 0";
        if ($helps) {
            $sql .= "      AND r.stringid LIKE '%\\\\_help'";
        }
        $sql .= " ORDER BY r.component, r.stringid, r.lang, r.branch";

        $params = array_merge(
            $inner_paramsbranches, $inner_paramslanguages, $inner_paramcomponents,
            $outer_paramsbranches, $outer_paramslanguages, $outer_paramcomponents
        );

        $rs = $DB->get_recordset_sql($sql, $params);
        $s = array();
        foreach($rs as $r) {
            if (!isset($s[$r->lang])) {
                $s[$r->lang] = array();
            }
            if (!isset($s[$r->lang][$r->component])) {
                $s[$r->lang][$r->component] = array();
            }
            if (!isset($s[$r->lang][$r->component][$r->stringid])) {
                $s[$r->lang][$r->component][$r->stringid] = array();
            }
            if (!isset($s[$r->lang][$r->component][$r->stringid][$r->branch])) {
                $string = new stdclass();
                $string->amosid = $r->id;
                $string->text = $r->text;
                $string->timemodified = $r->timemodified;
                $s[$r->lang][$r->component][$r->stringid][$r->branch] = $string;
            }
        }
        $rs->close();

        if (isset($s['en'])) {
            foreach ($s['en'] as $component => $t) {
                foreach ($t as $stringid => $u) {
                    foreach ($u as $branchcode => $english) {
                        reset($languages);
                        foreach ($languages as $lang) {
                            if ($lang == 'en') {
                                continue;
                            }
                            $string = new stdclass();
                            $string->branch = mlang_version::by_code($branchcode)->label;
                            $string->language = $lang;
                            $string->component = $component;
                            $string->stringid = $stringid;
                            $string->metainfo = ''; // todo read metainfo from database
                            $string->original = $english->text;
                            $string->originalid = $english->amosid;
                            $string->originalmodified = $english->timemodified;
                            if (isset($s[$lang][$component][$stringid][$branchcode])) {
                                $string->translation = $s[$lang][$component][$stringid][$branchcode]->text;
                                $string->translationid = $s[$lang][$component][$stringid][$branchcode]->amosid;
                                $string->timemodified = $s[$lang][$component][$stringid][$branchcode]->timemodified;
                            } else {
                                $string->translation = null;
                                $string->translationid = null;
                                $string->timemodified = null;
                            }
                            unset($s[$lang][$component][$stringid][$branchcode]);
                            if (!empty($substring)) {
                                // if defined, then either English or the translation must contain the substring
                                if (!strstr($string->original, $substring) and !strstr($string->translation, $substring)) {
                                    continue;
                                }
                            }
                            if (!empty($missing)) {
                                // missing or outdated string only
                                // English UTF-8 strings were added on Mon Feb 6 09:28:59 2006 by moodler (1139218139)
                                // UTF-8 translations were added earlier on Tue Dec 6 23:13:15 2005 by Eloy. Therefore
                                // if the original was part of the initial Martin's commit, it is considered to be older
                                // even if the timemodified is later. Otherwise AMOS would report almost all strings
                                // outdated
                                if (!empty($string->translation)) {
                                    if (($string->originalmodified == 1139218139) or ($string->originalmodified < $string->timemodified)) {
                                        // such a string is considered up-to-date
                                        continue;
                                    }
                                }
                            }
                            $this->strings[] = $string;
                        }
                    }
                }
            }
        }
    }

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
     * Decodes the identifier encoded by {@see self::encode_identifier()}
     *
     * @param string $encoded
     * @return array of (int)amosid_original, (int)amosid_translation
     */
    public static function decode_identifier($encoded) {
        $parts = split('_', $encoded, 3);
        if (($parts[0] !== 's') || (count($parts) < 2)) {
            throw new coding_exception('Invalid encoded identifier supplied');
        }
        $result = array();
        $result[0] = $parts[1]; // amosid_original
        if (isset($parts[2])) {
            $result[1] = $parts[2];
        } else {
            $result[1] = null;
        }
        return $result;
    }

}

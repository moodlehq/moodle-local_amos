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
        $this->fields = array('version', 'language', 'component', 'missing', 'helps', 'substring', 'stringid', 'stagedonly', 'page');
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

        // pagination is not part of POSTed form, can't use get_data_submitted() therefore
        $page = optional_param('fpg', null, PARAM_INT);
        if (!empty($page)) {
            $data->page = $page;
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
        if (is_null($data->stringid)) {
            $data->stringid = '';
        }
        if (is_null($data->stagedonly)) {
            $data->stagedonly = false;
        }
        if (is_null($data->page)) {
            $data->page = 1;
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
        $data->stringid     = trim(optional_param('fsid', '', PARAM_STRINGID));
        $data->stagedonly   = optional_param('fstg', false, PARAM_BOOL);

        return $data;
    }
}

/**
 * Represents the translation tool
 */
class local_amos_translator implements renderable {

    /** @const int number of rows per page */
    const PERPAGE = 100;

    /** @var int total number of the rows int the table */
    public $numofrows = 0;

    /** @var total number of untranslated strings */
    public $numofmissing = 0;

    /** @var int */
    public $currentpage = 1;

    /** @var array of stdclass strings to display */
    public $strings = array();

    /**
     * @param local_amos_filter $filter
     * @param stdclass $user working with the translator
     */
    public function __construct(local_amos_filter $filter, stdclass $user) {
        global $DB;

        // get the list of strings to display according the current filter values
        $branches   = $filter->get_data()->version;
        $languages  = array_merge(array('en'), $filter->get_data()->language);
        $components = $filter->get_data()->component;
        if (empty($branches) or empty($components) or empty($languages)) {
            return;
        }
        $missing    = $filter->get_data()->missing;
        $helps      = $filter->get_data()->helps;
        $substring  = $filter->get_data()->substring;
        $stringid   = $filter->get_data()->stringid;
        $stagedonly = $filter->get_data()->stagedonly;
        list($inner_sqlbranches, $inner_paramsbranches) = $DB->get_in_or_equal($branches, SQL_PARAMS_NAMED, 'innerbranch00000');
        list($inner_sqllanguages, $inner_paramslanguages) = $DB->get_in_or_equal($languages, SQL_PARAMS_NAMED, 'innerlang00000');
        list($inner_sqlcomponents, $inner_paramcomponents) = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED, 'innercomp00000');
        list($outer_sqlbranches, $outer_paramsbranches) = $DB->get_in_or_equal($branches, SQL_PARAMS_NAMED, 'outerbranch00000');
        list($outer_sqllanguages, $outer_paramslanguages) = $DB->get_in_or_equal($languages, SQL_PARAMS_NAMED, 'outerlang00000');
        list($outer_sqlcomponents, $outer_paramcomponents) = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED, 'outercomp00000');
        $sql = "SELECT r.id, r.branch, r.lang, r.component, r.stringid, r.text, r.timemodified, r.timeupdated
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
        } else {
            $sql .= "      AND r.stringid NOT LIKE '%\\\\_link'";
        }
        if ($stringid) {
            $sql .= "      AND r.stringid = :stringid";
            $params = array('stringid' => $stringid);
        } else {
            $params = array();
        }
        $sql .= " ORDER BY r.component, r.stringid, r.lang, r.branch";

        $params = array_merge($params,
            $inner_paramsbranches, $inner_paramslanguages, $inner_paramcomponents,
            $outer_paramsbranches, $outer_paramslanguages, $outer_paramcomponents
        );

        $rs = $DB->get_recordset_sql($sql, $params);
        $s = array();
        foreach($rs as $r) {
            if (!isset($s[$r->lang][$r->component][$r->stringid][$r->branch])) {
                $string = new stdclass();
                $string->amosid = $r->id;
                $string->text = $r->text;
                $string->timemodified = $r->timemodified;
                $string->timeupdated = $r->timeupdated;
                $s[$r->lang][$r->component][$r->stringid][$r->branch] = $string;
            }
        }
        $rs->close();

        // replace the loaded values with those already staged
        $stage = mlang_persistent_stage::instance_for_user($user->id, $user->sesskey);
        foreach($stage->get_iterator() as $component) {
            foreach ($component->get_iterator() as $staged) {
                $string = new stdclass();
                $string->amosid = null;
                $string->text = $staged->text;
                $string->timemodified = $staged->timemodified;
                $string->timeupdated = $staged->timemodified;
                $string->class = 'staged';
                $s[$component->lang][$component->name][$staged->id][$component->version->code] = $string;
            }
        }

        $this->currentpage = $filter->get_data()->page;
        $from = ($this->currentpage - 1) * self::PERPAGE + 1;
        $to = $this->currentpage * self::PERPAGE;
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
                            $string->committable = false;
                            if (isset($s[$lang][$component][$stringid][$branchcode])) {
                                $string->translation = $s[$lang][$component][$stringid][$branchcode]->text;
                                $string->translationid = $s[$lang][$component][$stringid][$branchcode]->amosid;
                                $string->timemodified = $s[$lang][$component][$stringid][$branchcode]->timemodified;
                                $string->timeupdated = $s[$lang][$component][$stringid][$branchcode]->timeupdated;
                                if (isset($s[$lang][$component][$stringid][$branchcode]->class)) {
                                    $string->class = $s[$lang][$component][$stringid][$branchcode]->class;
                                } else {
                                    $string->class = 'translated';
                                }
                                if ($string->originalmodified > max($string->timemodified, $string->timeupdated)) {
                                    $string->outdated = true;
                                } else {
                                    $string->outdated = false;
                                }
                            } else {
                                $string->translation = null;
                                $string->translationid = null;
                                $string->timemodified = null;
                                $string->timeupdated = null;
                                $string->class = 'missing';
                                $string->outdated = false;
                            }
                            unset($s[$lang][$component][$stringid][$branchcode]);

                            if ($stagedonly and $string->class != 'staged') {
                                continue;   // do not display this string
                            }
                            if (!empty($substring)) {
                                // if defined, then either English or the translation must contain the substring
                                if (!stristr($string->original, $substring) and !stristr($string->translation, $substring)) {
                                    continue; // do not display this strings
                                }
                            }
                            if ($missing) {
                                // missing or outdated string only
                                if ($string->translation and !$string->outdated) {
                                    continue; // it is considered up-top-date - do not display it
                                }
                            }
                            $this->numofrows++;
                            if (is_null($string->translation)) {
                                $this->numofmissing++;
                            }
                            // keep just strings from the current page
                            if ($this->numofrows < $from or $this->numofrows > $to) {
                                unset($string);
                                continue;
                            }
                            $this->strings[] = $string;
                        }
                    }
                }
            }
        }
        $allowedlangs = mlang_tools::list_allowed_languages($user->id);
        foreach ($this->strings as $string) {
            if (!empty($allowedlangs['X']) or !empty($allowedlangs[$string->language])) {
                $string->committable = true;
            }
        }
    }

    /**
     * Given AMOS string id, returns the suitable name of HTTP parameter to hold the translation
     *
     * @see self::decode_identifier()
     * @param string $lang language code
     * @param int $amosid_original AMOS id of the English origin of the string
     * @param int $amosid_translation AMOS id of the string translation, if it exists
     * @return string to be safely used as a name of the textarea or HTTP parameter
     */
    public static function encode_identifier($lang, $amosid_original, $amosid_translation=null) {
        if (empty($amosid_original) && ($amosid_original !== 0)) {
            throw new coding_exception('Illegal AMOS string identifier passed');
        }
        return $lang . '___' . $amosid_original . '___' . $amosid_translation;
    }

    /**
     * Decodes the identifier encoded by {@see self::encode_identifier()}
     *
     * @param string $encoded
     * @return array of (string)lang, (int)amosid_original, (int)amosid_translation
     */
    public static function decode_identifier($encoded) {
        $parts = split('___', $encoded, 3);
        if (count($parts) < 2) {
            throw new coding_exception('Invalid encoded identifier supplied');
        }
        $result = array();
        $result[0] = $parts[0]; // lang code
        $result[1] = $parts[1]; // amosid_original
        if (isset($parts[2])) {
            $result[2] = $parts[2];
        } else {
            $result[2] = null;
        }
        return $result;
    }

}

/**
 * Represents the persistant stage to be displayed
 */
class local_amos_stage implements renderable {

    /** @var array of stdclass to be rendered */
    public $strings;

    /** @var stdclass holds the info needed to mimic a filter form */
    public $filterfields;

    /**
     * @param stdclass $user the owner of the stage
     */
    public function __construct(stdclass $user) {
        global $DB;

        $this->strings = array();
        $stage = mlang_persistent_stage::instance_for_user($user->id, $user->sesskey);
        $needed = array();  // describes all strings that we will have to load to displaye the stage

        foreach($stage->get_iterator() as $component) {
            foreach ($component->get_iterator() as $staged) {
                if (!isset($needed[$component->version->code][$component->lang][$component->name])) {
                    $needed[$component->version->code][$component->lang][$component->name] = array();
                }
                $needed[$component->version->code][$component->lang][$component->name][] = $staged->id;
                $needed[$component->version->code]['en'][$component->name][] = $staged->id;
                $string = new stdclass();
                $string->component = $component->name;
                $string->branch = $component->version->code;
                $string->version = $component->version->label;
                $string->language = $component->lang;
                $string->stringid = $staged->id;
                $string->text = $staged->text;
                $string->timemodified = $staged->timemodified;
                $string->original = null; // is populated in the next step
                $string->current = null; // dtto
                $string->new = $staged->text;
                $string->committable = false;
                $this->strings[] = $string;
            }
        }
        $fver = array();
        $flng = array();
        $fcmp = array();
        foreach ($needed as $branch => $languages) {
            $fver[$branch] = true;
            foreach ($languages as $language => $components) {
                $flng[$language] = true;
                foreach ($components as $component => $strings) {
                    $fcmp[$component] = true;
                    $needed[$branch][$language][$component] = mlang_component::from_snapshot($component,
                            $language, mlang_version::by_code($branch), null, false, false, $strings);
                }
            }
        }
        $this->filterfields->fver = array_keys($fver);
        $this->filterfields->flng = array_keys($flng);
        $this->filterfields->fcmp = array_keys($fcmp);
        $allowedlangs = mlang_tools::list_allowed_languages($user->id);
        foreach ($this->strings as $string) {
            if (!empty($allowedlangs['X']) or !empty($allowedlangs[$string->language])) {
                $string->committable = true;
            }
            $string->original = $needed[$string->branch]['en'][$string->component]->get_string($string->stringid)->text;
            if ($needed[$string->branch][$string->language][$string->component] instanceof mlang_component) {
                $string->current = $needed[$string->branch][$string->language][$string->component]->get_string($string->stringid);
                if ($string->current instanceof mlang_string) {
                    $string->current = $string->current->text;
                }
            }
        }
    }
}

/**
 * TODO: short description.
 *
 * TODO: long description.
 */
class local_amos_log implements renderable {

    /** @var array of commit records to be displayed in the log */
    public $commits = array();

    /**
     * TODO: short description.
     *
     */
    public function __construct() {
        global $DB;

        $commitids = $DB->get_records('amos_commits', array(), 'timecommitted DESC', 'id', 0, 50);
        list($csql, $params) = $DB->get_in_or_equal(array_keys($commitids));

        $sql = "SELECT r.id, c.source, c.timecommitted, c.commitmsg, c.commithash, c.userid, c.userinfo,
                       r.commitid, r.branch, r.lang, r.component, r.stringid, r.text, r.timemodified, r.deleted
                  FROM {amos_commits} c
                  JOIN {amos_repository} r
                    ON (c.id = r.commitid)
                 WHERE c.id $csql
              ORDER BY c.timecommitted DESC, r.branch DESC, r.lang, r.component, r.stringid";

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $r) {
            if (!isset($this->commits[$r->commitid])) {
                $commit = new stdclass();
                $commit->id = $r->commitid;
                $commit->source = $r->source;
                $commit->timecommitted = $r->timecommitted;
                $commit->commitmsg = $r->commitmsg;
                $commit->commithash = $r->commithash;
                $commit->userid = $r->userid;
                $commit->userinfo = $r->userinfo;
                $commit->strings = array();
                $this->commits[$r->commitid] = $commit;
            }
            $string = new stdclass();
            $string->branch = mlang_version::by_code($r->branch)->label;
            $string->component = $r->component;
            $string->lang = $r->lang;
            $string->stringid = $r->stringid;
            $string->deleted = $r->deleted;
            $this->commits[$r->commitid]->strings[] = $string;
        }
        $rs->close();
    }
}

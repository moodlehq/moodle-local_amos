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

define('AMOS_USER_MAINTAINER',  0);
define('AMOS_USER_CONTRIBUTOR', 1);

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

    /** @var moodle_url */
    protected $permalink = null;

    /** @var stdClass */
    protected $datadefault = null;

    /** @var stdClass|null */
    protected $datasubmitted = null;

    /** @var stdClass|null */
    protected $datapermalink = null;

    /**
     * Creates the filter and sets the default filter values
     *
     * @param moodle_url $handler filter form action URL
     */
    public function __construct(moodle_url $handler) {

        $this->fields = array(
            'version', 'language', 'component', 'missing', 'outdated', 'has', 'helps', 'substring',
            'stringid', 'stringidpartial', 'stagedonly','greylistedonly', 'withoutgreylisted', 'app', 'last', 'page',
            'substringregex', 'substringcs');
        $this->lazyformname = 'amosfilter';
        $this->handler = $handler;
        $this->datadefault = $this->get_data_default();
        $this->datasubmitted = $this->get_data_submitted();
        $this->datapermalink = $this->get_data_permalink();

        if (!is_null($this->datasubmitted)) {
            $this->log_usage($this->datasubmitted, $this->datadefault);
        }
    }

    /**
     * Returns the filter data
     *
     * @return object
     */
    public function get_data() {
        $data = new stdclass();

        $default    = $this->datadefault;
        $submitted  = $this->datasubmitted;
        $permalink  = $this->datapermalink;

        foreach ($this->fields as $field) {
            if (isset($submitted->{$field})) {
                $data->{$field} = $submitted->{$field};
            } else if (isset($permalink->{$field})) {
                $data->{$field} = $permalink->{$field};
            } else {
                $data->{$field} = $default->{$field};
            }
        }

        $page = optional_param('fpg', null, PARAM_INT);
        if (!empty($page)) {
            $data->page = $page;
        }

        // If the user did not check any version, use the latest one instead of none.
        if (empty($data->version)) {
            $data->version = mlang_version::latest_version()->code;
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
            $data->version = mlang_version::latest_version()->code;
        }

        if (is_null($data->language)) {
            $currentlanguage = current_language();
            if ($currentlanguage === 'en') {
                $currentlanguage = 'en_fix';
            }
            $data->language = array($currentlanguage);
        }
        if (is_null($data->component)) {
           $data->component = array();
        }
        if (is_null($data->missing)) {
           $data->missing = false;
        }
        if (is_null($data->outdated)) {
           $data->outdated = false;
        }
        if (is_null($data->has)) {
           $data->has = false;
        }
        if (is_null($data->helps)) {
           $data->helps = false;
        }
        if (is_null($data->substring)) {
            $data->substring = '';
        }
        if (is_null($data->stringidpartial)) {
            $data->stringidpartial = false;
        }
        if (is_null($data->substringregex)) {
            $data->substringregex = false;
        }
        if (is_null($data->substringcs)) {
            $data->substringcs = false;
        }
        if (is_null($data->stringid)) {
            $data->stringid = '';
        }
        if (is_null($data->stagedonly)) {
            $data->stagedonly = false;
        }
        if (is_null($data->greylistedonly)) {
            $data->greylistedonly = false;
        }
        if (is_null($data->withoutgreylisted)) {
            $data->withoutgreylisted = false;
        }
        if (is_null($data->app)) {
            $data->app = false;
        }
        if (is_null($data->last)) {
            $data->last = true;
        }
        if (is_null($data->page)) {
            $data->page = 1;
        }

        return $data;
    }

    /**
     * Returns the form data as submitted by the user
     *
     * @return object|null
     */
    protected function get_data_submitted() {

        $issubmitted = optional_param('__lazyform_' . $this->lazyformname, false, PARAM_BOOL);

        if (!$issubmitted) {
            return null;
        }

        require_sesskey();
        $data = new stdclass();

        $data->version = null;
        $data->last = optional_param('flast', false, PARAM_BOOL);
        if (!$data->last) {
            $fver = optional_param('fver', null, PARAM_INT);
            if ($fver !== null) {
                foreach (mlang_version::list_all() as $version) {
                    if ($version->code == $fver) {
                        $data->version = $version->code;
                    }
                }
            }
        }

        $data->language = array();
        $flng = optional_param_array('flng', null, PARAM_SAFEDIR);
        if (is_array($flng)) {
            foreach ($flng as $language) {
                // todo if valid language code
                $data->language[] = $language;
            }
        }

        $data->component = array();
        $fcmp = optional_param_array('fcmp', null, PARAM_FILE);
        if (is_array($fcmp)) {
            foreach ($fcmp as $component) {
                // todo if valid component
                $data->component[] = $component;
            }
        }

        $data->missing              = optional_param('fmis', false, PARAM_BOOL);
        $data->outdated             = optional_param('fout', false, PARAM_BOOL);
        $data->has                  = optional_param('fhas', false, PARAM_BOOL);
        $data->helps                = optional_param('fhlp', false, PARAM_BOOL);
        $data->substring            = optional_param('ftxt', '', PARAM_RAW);
        $data->substringregex       = optional_param('ftxr', false, PARAM_BOOL);
        $data->substringcs          = optional_param('ftxs', false, PARAM_BOOL);
        $data->stringidpartial      = optional_param('fsix', false, PARAM_BOOL);
        if ($data->stringidpartial) {
            // in this case, the stringid can start with a non a-z
            $data->stringid         = trim(optional_param('fsid', '', PARAM_NOTAGS));
        } else {
            $data->stringid         = trim(optional_param('fsid', '', PARAM_STRINGID));
        }
        $data->stagedonly           = optional_param('fstg', false, PARAM_BOOL);
        $data->greylistedonly       = optional_param('fglo', false, PARAM_BOOL);
        $data->withoutgreylisted    = optional_param('fwog', false, PARAM_BOOL);
        $data->app                  = optional_param('fapp', false, PARAM_BOOL);

        // reset the paginator to the first page every time the filter is saved
        $data->page = 1;

        if ($data->missing and $data->outdated) {
            // It does not make sense to have both.
            $data->missing = false;
        }

        return $data;
    }

    /**
     * Returns the form data as set by explicit permalink
     *
     * @see self::set_permalink()
     * @return object|null
     */
    protected function get_data_permalink() {

        $ispermalink = optional_param('t', false, PARAM_INT);
        if (empty($ispermalink)) {
            return null;
        }
        $data = new stdclass();

        $data->version = [];
        $fver = optional_param('v', '', PARAM_RAW);
        if ($fver !== 'l') {
            $fver = explode(',', $fver);
            $fver = clean_param_array($fver, PARAM_INT);
            if (!empty($fver) && is_array($fver)) {
                $fver = max($fver);
                foreach (mlang_version::list_all() as $version) {
                    if ($version->code == $fver) {
                        $data->version = $version->code;
                    }
                }
            }
        } else {
            $data->last = 1;
        }


        $data->language = array();
        $flng = optional_param('l', '', PARAM_RAW);
        if ($flng == '*') {
            // all languages
            foreach (mlang_tools::list_languages(false) as $langcode => $langname) {
                $data->language[] = $langcode;
            }
        } else {
            $flng = explode(',', $flng);
            $flng = clean_param_array($flng, PARAM_SAFEDIR);
            if (!empty($flng) and is_array($flng)) {
                foreach ($flng as $language) {
                    // todo if valid language code
                    if (!empty($language)) {
                        $data->language[] = $language;
                    }
                }
            }
        }

        $data->app = false;

        $data->component = array();
        $fcmp = optional_param('c', '', PARAM_RAW);
        if ($fcmp == '*std') {
            // standard components
            $data->component = array();
            foreach (local_amos_standard_plugins() as $plugins) {
                $data->component = array_merge($data->component, array_keys($plugins));
            }
        } else if ($fcmp == '*app') {
            // app components
            $data->component = array_keys(local_amos_app_plugins());
            $data->app       = true;
            $data->last = 1;
        } else if ($fcmp == '*') {
            // all components
            $data->component = array_keys(mlang_tools::list_components());
        } else {
            $fcmp = explode(',', $fcmp);
            $fcmp = clean_param_array($fcmp, PARAM_FILE);
            if (!empty($fcmp) and is_array($fcmp)) {
                foreach ($fcmp as $component) {
                    // todo if valid component
                    $data->component[] = $component;
                }
            }
        }

        $data->missing              = optional_param('m', false, PARAM_BOOL);
        $data->outdated             = optional_param('u', false, PARAM_BOOL);
        $data->has                  = optional_param('x', false, PARAM_BOOL);
        $data->helps                = optional_param('h', false, PARAM_BOOL);
        $data->substring            = optional_param('s', '', PARAM_RAW);
        $data->substringregex       = optional_param('r', false, PARAM_BOOL);
        $data->substringcs          = optional_param('i', false, PARAM_BOOL);
        $data->stringidpartial      = optional_param('p', false, PARAM_BOOL);
        if ($data->stringidpartial) {
            // in this case, the stringid can start with a non a-z
            $data->stringid         = trim(optional_param('d', '', PARAM_NOTAGS));
        } else {
            $data->stringid         = trim(optional_param('d', '', PARAM_STRINGID));
        }
        $data->stagedonly           = optional_param('g', false, PARAM_BOOL);
        $data->greylistedonly       = optional_param('o', false, PARAM_BOOL);
        $data->withoutgreylisted    = optional_param('w', false, PARAM_BOOL);
        $data->app                  = optional_param('a', $data->app, PARAM_BOOL);

        // reset the paginator to the first page for permalinks
        $data->page = 1;

        return $data;
    }

    /**
     * Prepare permanent link for the given filter data
     *
     * @param moodle_url $baseurl
     * @param stdClass $fdata as returned by {@see self::get_data()}
     * @return moodle_url $permalink
     */
    public function set_permalink(moodle_url $baseurl, stdClass $fdata) {

        $this->permalink = new moodle_url($baseurl, array('t' => time()));
        if ($fdata->last) {
            $this->permalink->param('v', 'l');
        } else {
            $this->permalink->param('v', $fdata->version);
        }


        // list of languages or '*' if all are selected
        $all = mlang_tools::list_languages(false);
        foreach ($fdata->language as $selected) {
            unset($all[$selected]);
        }
        if (empty($all)) {
            $this->permalink->param('l', '*');
        } else {
            $this->permalink->param('l', implode(',', $fdata->language));
        }
        unset($all);

        // list of components or '*' if all are selected
        $all = mlang_tools::list_components();
        $app = array_keys(local_amos_app_plugins());
        $app = array_combine($app, $app);
        foreach ($fdata->component as $selected) {
            unset($all[$selected]);
            unset($app[$selected]);
        }

        // *std cannot be preselected because it depends on version.
        if (empty($all)) {
            $this->permalink->param('c', '*');
        } else if (empty($app)) {
            $this->permalink->param('c', '*app');
        } else {
            $this->permalink->param('c', implode(',', $fdata->component));
        }
        unset($all);

        // substring and stringid
        $this->permalink->param('s', $fdata->substring);
        $this->permalink->param('d', $fdata->stringid);

        // checkboxes
        if ($fdata->missing)            $this->permalink->param('m', 1);
        if ($fdata->outdated)           $this->permalink->param('u', 1);
        if ($fdata->has)                $this->permalink->param('x', 1);
        if ($fdata->helps)              $this->permalink->param('h', 1);
        if ($fdata->substringregex)     $this->permalink->param('r', 1);
        if ($fdata->substringcs)        $this->permalink->param('i', 1);
        if ($fdata->stringidpartial)    $this->permalink->param('p', 1);
        if ($fdata->stagedonly)         $this->permalink->param('g', 1);
        if ($fdata->greylistedonly)     $this->permalink->param('o', 1);
        if ($fdata->withoutgreylisted)  $this->permalink->param('w', 1);
        if ($fdata->app)                $this->permalink->param('a', 1);

        return $this->permalink;
    }

    /**
     * @return null|moodle_url permanent link to the filter settings
     */
    public function get_permalink() {
        return $this->permalink;
    }

    /**
     * Logs the information about filter submitted data for usability analysis
     *
     * @param stdClass $submitted data as returned by {@link self::get_data_submitted()}
     * @param stdClass $default data as returned by {@link self::get_data_default()}
     */
    protected function log_usage(stdClass $submitted, stdClass $default) {
        global $DB, $USER;

        $data = (object)array(
            'timesubmitted' => time(),
            'sesskey' => sesskey(),
            'userid' => $USER->id,
            'userlang' => $USER->lang,
            'currentlang' => current_language(),
            'usercountry' => $USER->country,
            'ismaintainer' => has_capability('local/amos:commit', context_system::instance()),
            'usesdefaultversion' => (int)($submitted->version === $default->version),
            'usesdefaultlang' => (int)($submitted->language === $default->language),
            'numofversions' => (int)isset($submitted->version),
            'numoflanguages' => count($submitted->language),
            'numofcomponents' => count($submitted->component),
            'showmissingonly' => (int)$submitted->missing,
            'showoutdatedonly' => (int)$submitted->outdated,
            'showexistingonly' => (int)$submitted->has,
            'showhelpsonly' => (int)$submitted->helps,
            'withsubstring' => (int)($submitted->substring !== ''),
            'substringregex' => (int)$submitted->substringregex,
            'substringcasesensitive' => (int)$submitted->substringcs,
            'withstringid' => (int)($submitted->stringid !== ''),
            'stringidpartial' => (int)$submitted->stringidpartial,
            'showstagedonly' => (int)$submitted->stagedonly,
            'showgreylistedonly' => (int)$submitted->greylistedonly,
            'showwithoutgreylisted' => (int)$submitted->withoutgreylisted,
            'app' => (int)$submitted->app,
            'page' => $submitted->page,

        );

        $DB->insert_record('amos_filter_usage', $data, false);
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

        // Get the list of strings to display according the current filter values.
        $last       = $filter->get_data()->last;
        $version    = $filter->get_data()->version;
        $languages  = $filter->get_data()->language;
        $components = $filter->get_data()->component;

        if ((!$last && empty($version)) || empty($components) || empty($languages)) {
            return;
        }

        $missing            = $filter->get_data()->missing;
        $outdated           = $filter->get_data()->outdated;
        $has                = $filter->get_data()->has;
        $helps              = $filter->get_data()->helps;
        $substring          = $filter->get_data()->substring;
        $substringregex     = $filter->get_data()->substringregex;
        $substringcs        = $filter->get_data()->substringcs;
        $stringid           = $filter->get_data()->stringid;
        $stringidpartial    = $filter->get_data()->stringidpartial;
        $stagedonly         = $filter->get_data()->stagedonly;
        $withoutgreylisted  = $filter->get_data()->withoutgreylisted;
        $app                = $filter->get_data()->app;

        // Prepare the SQL queries to load all the filtered strings and their translations.
        $params = [];

        list($sqllanguages, $paramlanguages) = $DB->get_in_or_equal($languages, SQL_PARAMS_NAMED);
        list($sqlcomponents1, $paramcomponents1) = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED);
        list($sqlcomponents2, $paramcomponents2) = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED);

        $params += $paramlanguages;
        $params += $paramcomponents1;
        $params += $paramcomponents2;

        if ($last) {
            // Get know the most recent branch for every component.
            $latestcomponentbranch = $DB->get_records_select_menu('amos_strings',  "component $sqlcomponents1 GROUP BY component",
                $paramcomponents1,  '', "component, MAX(since) as branch");
        }

        // Get all the English strings and translations for the translator.

        $sql1 = "SELECT id, since, 'en' AS lang, component, strname, strtext, timemodified
                   FROM {amos_strings}
                  WHERE component $sqlcomponents1";

        $sql2 = "SELECT id, since, lang, component, strname, strtext, timemodified
                   FROM {amos_translations}
                  WHERE component $sqlcomponents2
                    AND lang $sqllanguages";

        if ($stringid) {
            if ($stringidpartial) {
                $sql1 .= " AND " . $DB->sql_like("strname", ":strname1", false);
                $sql2 .= " AND " . $DB->sql_like("strname", ":strname2", false);
                $params['strname1'] = '%' . $DB->sql_like_escape($stringid) . '%';
                $params['strname2'] = '%' . $DB->sql_like_escape($stringid) . '%';

            } else {
                $sql1 .= " AND strname = :strname1";
                $sql2 .= " AND strname = :strname2";
                $params['strname1'] = $stringid;
                $params['strname2'] = $stringid;
            }
        }

        if ($helps) {
            $sql1 .= " AND " . $DB->sql_like("strname", ":helpstrname1", false);
            $sql2 .= " AND " . $DB->sql_like("strname", ":helpstrname2", false);
            $params['helpstrname1'] = '%' . $DB->sql_like_escape('_help');
            $params['helpstrname2'] = '%' . $DB->sql_like_escape('_help');

        } else {
            $sql1 .= " AND " . $DB->sql_like("strname", ":linkstrname1", false, true, true);
            $sql2 .= " AND " . $DB->sql_like("strname", ":linkstrname2", false, true, true);
            $params['linkstrname1'] = '%' . $DB->sql_like_escape('_link');
            $params['linkstrname2'] = '%' . $DB->sql_like_escape('_link');
        }

        $sql = "SELECT id, since, lang, component, strname, strtext, timemodified
                  FROM ($sql1 UNION $sql2) s";

        $recordset = $DB->get_recordset_sql($sql, $params);

        // Iterate over the results and pick the most recent string value for each selected branch.
        $tree = [];
        $newer = [];

        foreach ($recordset as $record) {
            if ($last) {
                $compbranch = $latestcomponentbranch[$record->component];
            } else {
                $compbranch = $version;
            }

            if ($record->since > $compbranch) {
                // Make a note that more recent version exists.
                $newer[$record->lang][$record->component][$record->strname] = true;
                continue;
            }
            if (!isset($tree[$record->lang][$record->component][$record->strname])) {
                $tree[$record->lang][$record->component][$record->strname] = $record;

            } else {
                $current = $tree[$record->lang][$record->component][$record->strname];

                if ($record->since < $current->since) {
                    continue;
                }

                if (($record->since == $current->since) && ($record->timemodified < $current->timemodified)) {
                    continue;
                }

                if (($record->since == $current->since) && ($record->timemodified == $current->timemodified)
                        && ($record->id < $current->id)) {
                    continue;
                }

                $tree[$record->lang][$record->component][$record->strname] = $record;
            }
        }

        $recordset->close();

        // Convert the tree into a new one containing only non-deletions.
        $s = [];

        foreach($tree as $xlang => $xcomps) {
            foreach ($xcomps as $xcomp => $xstrnames) {
                foreach ($xstrnames as $xstrname => $record) {
                    if ($record->strtext !== null) {
                        $s[$xlang][$xcomp][$xstrname] = (object) [
                            'amosid' => $record->id,
                            'text' => $record->strtext,
                            'since' => $record->since,
                            'timemodified' => $record->timemodified,
                            'islatest' => empty($newer[$xlang][$xcomp][$xstrname]),
                        ];
                    }
                    unset($tree[$xlang][$xcomp][$xstrname]);
                }
            }
        }
        unset($tree);

        foreach ($s as $xlang => &$xcomps) {
            ksort($xcomps);
            foreach ($xcomps as $xcomp => &$xstrnames) {
                ksort($xstrnames);
            }
        }

        // Replace the loaded values with those already staged.
        $stage = mlang_persistent_stage::instance_for_user($user->id, $user->sesskey);
        foreach ($stage as $component) {
            foreach ($component as $staged) {
                if ($staged->component->version->code > $compbranch) {
                    continue;
                }
                $string = new stdclass();
                $string->amosid = null;
                $string->text = $staged->text;
                $string->timemodified = $staged->timemodified;
                $string->class = 'staged';
                $string->nocleaning = $staged->nocleaning;
                $s[$component->lang][$component->name][$staged->id] = $string;
            }
        }

        $this->currentpage = $filter->get_data()->page;
        $from = ($this->currentpage - 1) * self::PERPAGE + 1;
        $to = $this->currentpage * self::PERPAGE;
        if (isset($s['en'])) {
            foreach ($s['en'] as $component => $t) {
                foreach ($t as $stringid => $english) {
                    reset($languages);
                    foreach ($languages as $lang) {
                        if ($lang == 'en') {
                            continue;
                        }
                        $string = new stdclass();
                        $versince = mlang_version::by_code($english->since);
                        $string->englishsincedir = $versince->dir;
                        $string->englishsincecode = $versince->code;
                        $string->englishsincelabel = $versince->label;
                        $string->islatest = $english->islatest;
                        $string->language = $lang;
                        $string->component = $component;
                        $string->stringid = $stringid;
                        $string->metainfo = ''; // todo read metainfo from database
                        $string->original = $english->text;
                        $string->originalid = $english->amosid;
                        $string->originalmodified = $english->timemodified;
                        $string->committable = false;
                        if (isset($s[$lang][$component][$stringid])) {
                            $string->translation = $s[$lang][$component][$stringid]->text;
                            $string->translationid = $s[$lang][$component][$stringid]->amosid;
                            $string->timemodified = $s[$lang][$component][$stringid]->timemodified;
                            $versince = mlang_version::by_code($s[$lang][$component][$stringid]->since);
                            $string->translationsincedir = $versince->dir;
                            $string->translationsincecode = $versince->code;
                            $string->translationsincelabel = $versince->label;
                            if (isset($s[$lang][$component][$stringid]->class)) {
                                $string->class = $s[$lang][$component][$stringid]->class;
                            } else {
                                $string->class = 'translated';
                            }
                            if ($string->originalmodified > $string->timemodified) {
                                $string->outdated = true;
                            } else {
                                $string->outdated = false;
                            }
                            if (isset($s[$lang][$component][$stringid]->nocleaning)) {
                                $string->nocleaning = $s[$lang][$component][$stringid]->nocleaning;
                            } else {
                                $string->nocleaning = false;
                            }
                        } else {
                            $string->translation = null;
                            $string->translationid = null;
                            $string->timemodified = null;
                            $string->translationsincedir = null;
                            $string->translationsincecode = null;
                            $string->translationsincelabel = null;
                            $string->class = 'missing';
                            $string->outdated = false;
                            $string->nocleaning = false;
                        }

                        $applist = local_amos_applist_strings();

                        if ($component == 'local_moodlemobileapp') {
                            $string->app = $stringid;
                        } else if (isset($applist[$component.'/'.$stringid])) {
                            $string->app = $applist[$component.'/'.$stringid];
                        } else {
                            $string->app = false;
                        }

                        unset($s[$lang][$component][$stringid]);

                        if ($has and is_null($string->translation)) {
                            continue;
                        }
                        if ($stagedonly and $string->class != 'staged') {
                            continue;   // do not display this string
                        }
                        if ($app and !$string->app) {
                            continue;   // do not display this string
                        }
                        if (!empty($substring)) {
                            // if defined, then either English or the translation must contain the substring
                            if (empty($substringregex)) {
                                if (empty($substringcs)) {
                                    if (!stristr($string->original, trim($substring)) and !stristr($string->translation, trim($substring))) {
                                        continue; // do not display this strings
                                    }
                                } else {
                                    if (!strstr($string->original, trim($substring)) and !strstr($string->translation, trim($substring))) {
                                        continue; // do not display this strings
                                    }
                                }
                            } else {
                                // considered substring a regular expression
                                if (empty($substringcs)) {
                                    if (!preg_match("/$substring/i", $string->original) and !preg_match("/$substring/i", $string->translation)) {
                                        continue;
                                    }
                                } else {
                                    if (!preg_match("/$substring/", $string->original) and !preg_match("/$substring/", $string->translation)) {
                                        continue;
                                    }
                                }
                            }
                        }
                        if ($missing) {
                            if ($string->class === 'translated' and !$string->outdated) {
                                continue;
                            }
                        }
                        if ($outdated) {
                            if (!$string->outdated) {
                                continue;
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
        $allowedlangs = mlang_tools::list_allowed_languages($user->id);
        foreach ($this->strings as $string) {
            if (!empty($allowedlangs['X']) or !empty($allowedlangs[$string->language])) {
                $string->committable = true;
            }
            $string->translatable = true;
        }
        $standard = local_amos_standard_plugins();
        foreach ($this->strings as $string) {
            if ($string->language === 'en_fix') {
                if (!isset($standard[$string->branchdir][$string->component])) {
                    $string->committable = false;
                    $string->translatable = false;
                    $string->translation = get_string('unableenfixaddon', 'local_amos');
                    $string->class = 'missing explanation';
                }
            }
        }
        foreach ($this->strings as $string) {
            if ($string->language === 'en_fix' and $string->component === 'countries') {
                $string->committable = false;
                $string->translatable = false;
                $string->translation = get_string('unableenfixcountries', 'local_amos');
                $string->class = 'missing explanation';
           }
        }

        // Check that the language has a maintainer to eventually commit the contribution.
        $maintainedlangscache = cache::make_from_params(cache_store::MODE_APPLICATION, 'local_amos', 'maintainedlangs');

        $maintainedlangs = $maintainedlangscache->get('maintainedlangs');

        if ($maintainedlangs === false) {
            $sql = "SELECT DISTINCT lang
                      FROM {amos_translators}
                     WHERE status = :maintainer";
            $maintainedlangs = array_flip($DB->get_fieldset_sql($sql, ['maintainer' => AMOS_USER_MAINTAINER]));
            $maintainedlangscache->set('maintainedlangs', $maintainedlangs);
        }

        $langnames = mlang_tools::list_languages();

        foreach ($this->strings as $string) {
            if (!isset($maintainedlangs[$string->language]) && !$string->committable) {
                $string->translatable = false;
                $string->translation = get_string('unableunmaintained', 'local_amos', $langnames[$string->language]);
                $string->class = 'missing explanation';
            }
        }
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

    /** $var local_amos_importfile_form form to import data */
    public $importform;

    /** @var local_amos_merge_form to merge strings form another branch */
    public $mergeform;

    /** @var local_amos_diff_form to stage differences between two branches */
    public $diffform;

    /** @var local_amos_execute_form to execute a given AMOScript */
    public $executeform;

    /** @var pre-set commit message */
    public $presetmessage;

    /** @var stdClass if the stage comes from an applied contribution, this object holds the id and the contributor */
    public $stagedcontribution;

    /** @var bool */
    public $cancommit = false;

    /** @var bool */
    public $canstash = false;

    /**
     * @param stdclass $user the owner of the stage
     */
    public function __construct(stdclass $user) {
        global $DB;

        $stringstree = array(); // tree of strings to simulate ORDER BY component, stringid, lang, branch
        $this->strings = array(); // final list populated from the stringstree
        $stage = mlang_persistent_stage::instance_for_user($user->id, $user->sesskey);
        $needed = array();  // describes all strings that we will have to load to displaye the stage

        if (has_capability('local/amos:importfile', context_system::instance(), $user)) {
            $this->importform = new local_amos_importfile_form(new moodle_url('/local/amos/importfile.php'), local_amos_importfile_options());
        }

        if (has_capability('local/amos:commit', context_system::instance(), $user)) {
            $this->cancommit = true;
            $this->mergeform = new local_amos_merge_form(new moodle_url('/local/amos/merge.php'), local_amos_merge_options());
            $this->diffform = new local_amos_diff_form(new moodle_url('/local/amos/diff.php'), local_amos_diff_options());
        }

        if (has_capability('local/amos:stash', context_system::instance(), $user)) {
            $this->canstash = true;
        }

        if (has_all_capabilities(array('local/amos:execute', 'local/amos:stage'), context_system::instance(), $user)) {
            $this->executeform = new local_amos_execute_form(new moodle_url('/local/amos/execute.php'), local_amos_execute_options());
        }

        foreach($stage as $component) {
            foreach ($component as $staged) {
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
                $string->deleted = $staged->deleted;
                $string->original = null; // is populated in the next step
                $string->current = null; // dtto
                $string->new = $staged->text;
                $string->committable = false;
                if (isset($staged->nocleaning)) {
                    $string->nocleaning = $staged->nocleaning;
                } else {
                    $string->nocleaning = false;
                }
                $stringstree[$string->component][$string->stringid][$string->language][$string->branch] = $string;
            }
        }

        // order by component
        ksort($stringstree);
        foreach ($stringstree as $subtree) {
            // order by stringid
            ksort($subtree);
            foreach ($subtree as $subsubtree) {
                // order by language
                ksort($subsubtree);
                foreach ($subsubtree as $subsubsubtree) {
                    // order by branch
                    ksort($subsubsubtree);
                    foreach ($subsubsubtree as $string) {
                        $this->strings[] = $string;
                    }
                }
            }
        }

        unset($stringstree);

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
        $this->filterfields = new stdClass();
        $this->filterfields->fver = null;
        if (!empty($fver) && is_array($fver)) {
            $this->filterfields->fver = max(array_keys($fver));
        }
        $this->filterfields->flng = array_keys($flng);
        $this->filterfields->fcmp = array_keys($fcmp);
        $allowedlangs = mlang_tools::list_allowed_languages($user->id);
        foreach ($this->strings as $string) {
            if (!empty($allowedlangs['X']) or !empty($allowedlangs[$string->language])) {
                $string->committable = true;
            }
            if (!$needed[$string->branch]['en'][$string->component]->has_string($string->stringid)) {
                $string->original = '*DELETED*';
            } else {
                $string->original = $needed[$string->branch]['en'][$string->component]->get_string($string->stringid)->text;
            }
            if ($needed[$string->branch][$string->language][$string->component] instanceof mlang_component) {
                $string->current = $needed[$string->branch][$string->language][$string->component]->get_string($string->stringid);
                if ($string->current instanceof mlang_string) {
                    $string->current = $string->current->text;
                }
            }
            if (empty(mlang_version::by_code($string->branch)->translatable)) {
                $string->committable = false;
            }
        }
    }
}

/**
 * Represents a collection commits to be shown at the AMOS Log page
 */
class local_amos_log implements renderable {

    const LIMITCOMMITS = 1000;

    /** @var array of commit records to be displayed in the log */
    public $commits = array();

    /** @var int number of found commits */
    public $numofcommits = null;

    /** @var int number of filtered strings modified by filtered commits */
    public $numofstrings = null;

    /**
     * Fetches the required commits from the repository
     *
     * @param array $filter allows to filter commits
     */
    public function __construct(array $filter = array()) {
        global $DB;

        // we can not use limits inside subquery so firstly let us get commits we are interested in
        $params     = array();
        $where      = array();
        $getsql     = "SELECT id";
        $countsql   = "SELECT COUNT(*)";
        $sql        = "  FROM {amos_commits}";

        if (!empty($filter['userid'])) {
            $where['userid'] = "userid = ?";
            $params[] = $filter['userid'];
        }

        if (!empty($filter['userinfo'])) {
            $where['userinfo'] = $DB->sql_like('userinfo', '?', false, false);
            $params[] = '%'.$DB->sql_like_escape($filter['userinfo']).'%';
        }

        if (!empty($where['userinfo']) and !empty($where['userid'])) {
            $where['user'] = '(' . $where['userid'] . ') OR (' . $where['userinfo'] . ')';
            unset($where['userinfo']);
            unset($where['userid']);
        }

        if (!empty($filter['committedafter'])) {
            $where['committedafter'] = 'timecommitted >= ?';
            $params[] = $filter['committedafter'];
        }

        if (!empty($filter['committedbefore'])) {
            $where['committedbefore'] = 'timecommitted < ?';
            $params[] = $filter['committedbefore'];
        }

        if (!empty($filter['source'])) {
            $where['source'] = 'source = ?';
            $params[] = $filter['source'];
        }

        if (!empty($filter['commitmsg'])) {
            $where['commitmsg'] = $DB->sql_like('commitmsg', '?', false, false);
            $params[] = '%'.$DB->sql_like_escape($filter['commitmsg']).'%';
        }

        if (!empty($filter['commithash'])) {
            $where['commithash'] = $DB->sql_like('commithash', '?', false, false);
            $params[] = $DB->sql_like_escape($filter['commithash']).'%';
        }

        if ($where) {
            $where = '(' . implode(') AND (', $where) . ')';
            $sql .= " WHERE $where";
        }

        $ordersql = " ORDER BY timecommitted DESC, id DESC";

        $this->numofcommits = $DB->count_records_sql($countsql.$sql, $params);

        $commitids = $DB->get_records_sql($getsql.$sql.$ordersql, $params, 0, self::LIMITCOMMITS);

        if (empty($commitids)) {
            // nothing to load
            return;
        }
        // now get all repository records modified by these commits
        // and optionally filter them if requested

        $params = array();
        list($csql, $params) = $DB->get_in_or_equal(array_keys($commitids));

        if (!empty($filter['branch'])) {
            list($branchsql, $branchparams) = $DB->get_in_or_equal(array_keys($filter['branch']));
        } else {
            $branchsql = '';
        }

        if (!empty($filter['lang'])) {
            list($langsql, $langparams) = $DB->get_in_or_equal($filter['lang']);
        } else {
            $langsql = '';
        }

        if (!empty($filter['component'])) {
            list($componentsql, $componentparams) = $DB->get_in_or_equal($filter['component']);
        } else {
            $componentsql = '';
        }

        $countsql   = "SELECT COUNT(r.id)";
        $getsql     = "SELECT r.id, c.source, c.timecommitted, c.commitmsg, c.commithash, c.userid, c.userinfo,
                              r.commitid, r.branch, r.lang, r.component, r.stringid, t.text, r.timemodified, r.deleted";
        $sql        = "  FROM {amos_commits} c
                         JOIN {amos_repository} r ON (c.id = r.commitid)
                         JOIN {amos_texts} t ON (r.textid = t.id)
                        WHERE c.id $csql";

        if ($branchsql) {
            $sql .= " AND r.branch $branchsql";
            $params = array_merge($params, $branchparams);
        }

        if ($langsql) {
            $sql .= " AND r.lang $langsql";
            $params = array_merge($params, $langparams);
        }

        if ($componentsql) {
            $sql .= " AND r.component $componentsql";
            $params = array_merge($params, $componentparams);
        }

        if (!empty($filter['stringid'])) {
            $sql .= " AND r.stringid = ?";
            $params[] = $filter['stringid'];
        }

        $ordersql = " ORDER BY c.timecommitted DESC, c.id DESC, r.branch DESC, r.lang, r.component, r.stringid";

        $this->numofstrings = $DB->count_records_sql($countsql.$sql, $params);

        $rs = $DB->get_recordset_sql($getsql.$sql.$ordersql, $params);

        $numofcommits = 0;

        foreach ($rs as $r) {
            if (!isset($this->commits[$r->commitid])) {
                if ($numofcommits == self::LIMITCOMMITS) {
                    // we already have enough
                    break;
                }
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
                $numofcommits++;
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

/**
 * Represents data to be displayed at http://download.moodle.org/langpack/x.x/ index page
 */
class local_amos_index_page implements renderable {

    /** @var mlang_version */
    public $version = null;

    /** @var array */
    public $langpacks = array();

    /** @var int */
    public $timemodified;

    /** @var number of strings in the English official language pack */
    public $totalenglish = 0;

    /** @var number of available lang packs (without English) */
    public $numoflangpacks = 0;

    /** @var number of lang packs having more that xx% of the string translated */
    public $percents = array();

    /** @var array */
    protected $packinfo = array();

    /**
     * Initialize data
     *
     * @param mlang_version $version we are generating page for
     * @param array $packinfo data structure prepared by cli/export-zip.php
     */
    public function __construct(mlang_version $version, array $packinfo) {

        $this->version  = $version;
        $this->packinfo = fullclone($packinfo);
        $this->timemodified = time();
        $this->percents = array('0' => 0, '40' => 0, '60' => 0, '80' => 0); // percents => number of langpacks
        // get the number of strings for standard plugins
        // only the standard plugins are taken into statistics calculation
        $standard = local_amos_standard_plugins();
        $english = array(); // holds the number of English strings per component

        foreach ($standard[$this->version->dir] as $componentname => $unused) {
            $component = mlang_component::from_snapshot($componentname, 'en', $this->version);
            $english[$componentname] = $component->get_number_of_strings(true);
            $this->totalenglish += $english[$componentname];
            $component->clear();
        }

        foreach ($this->packinfo as $langcode => $info) {
            if ($langcode !== 'en') {
                $this->numoflangpacks++;
            }
            $langpack = new stdclass();
            $langpack->langname = $info['langname'];
            $langpack->filename = $langcode.'.zip';
            $langpack->filesize = $info['filesize'];
            $langpack->modified = $info['modified'];
            if (!empty($info['parent'])) {
                $langpack->parent = $info['parent'];
            } else {
                $langpack->parent = 'en';
            }
            // calculate the translation statistics
            if ($langpack->parent == 'en') {
                $langpack->totaltranslated = 0;
                foreach ($info['numofstrings'] as $component => $translated) {
                    if (isset($standard[$this->version->dir][$component])) {
                        $langpack->totaltranslated += min($translated, $english[$component]);
                    }
                }
                if ($this->totalenglish == 0) {
                    $langpack->ratio = null;
                } else {
                    $langpack->ratio = $langpack->totaltranslated / $this->totalenglish;
                    if ($langpack->ratio > 0.8) {
                        $this->percents['80']++;
                    } elseif ($langpack->ratio > 0.6) {
                        $this->percents['60']++;
                    } elseif ($langpack->ratio > 0.4) {
                        $this->percents['40']++;
                    } else {
                        $this->percents['0']++;
                    }
                }
            } else {
                $langpack->totaltranslated = 0;
                foreach ($info['numofstrings'] as $component => $translated) {
                    $langpack->totaltranslated += $translated;
                }
                $langpack->ratio = null;
            }
            $this->langpacks[$langcode] = $langpack;
        }
    }
}

/**
 * Represents data to be displayed at http://download.moodle.org/langpack/x.x/ html page.
 * @todo This will eventually replace local_amos_index_page when MDLSITE-2784 is completed.
 */
class local_amos_index_tablehtml extends local_amos_index_page {}

/**
 * Renderable stash
 */
class local_amos_stash implements renderable {

    /** @var int identifier in the table of stashes */
    public $id;
    /** @var string title of the stash */
    public $name;
    /** @var int timestamp of when the stash was created */
    public $timecreated;
    /** @var stdClass the owner of the stash */
    public $owner;
    /** @var array of language names */
    public $languages = array();
    /** @var array of component names */
    public $components = array();
    /** @var int number of stashed strings */
    public $strings = 0;
    /** @var bool is autosave stash */
    public $isautosave;

    /** @var array of stdClasses representing stash actions */
    protected $actions = array();

    /**
     * Factory method using an instance if {@link mlang_stash} as a data source
     *
     * @param mlang_stash $stash
     * @param stdClass $owner owner user data
     * @return local_amos_stash new instance
     */
    public static function instance_from_mlang_stash(mlang_stash $stash, stdClass $owner) {

        if ($stash->ownerid != $owner->id) {
            throw new coding_exception('Stash owner mismatch');
        }

        $new                = new local_amos_stash();
        $new->id            = $stash->id;
        $new->name          = $stash->name;
        $new->timecreated   = $stash->timecreated;

        $stage = new mlang_stage();
        $stash->apply($stage);
        list($new->strings, $new->languages, $new->components) = mlang_stage::analyze($stage);
        $stage->clear();
        unset($stage);

        $new->components    = explode('/', trim($new->components, '/'));
        $new->languages     = explode('/', trim($new->languages, '/'));

        $new->owner         = $owner;

        if ($stash->hash === 'xxxxautosaveuser'.$new->owner->id) {
            $new->isautosave = true;
        } else {
            $new->isautosave = false;
        }

        return $new;
    }

    /**
     * Factory method using plain database record from amos_stashes table as a source
     *
     * @param stdClass $record stash record from amos_stashes table
     * @param stdClass $owner owner user data
     * @return local_amos_stash new instance
     */
    public static function instance_from_record(stdClass $record, stdClass $owner) {

        if ($record->ownerid != $owner->id) {
            throw new coding_exception('Stash owner mismatch');
        }

        $new                = new local_amos_stash();
        $new->id            = $record->id;
        $new->name          = $record->name;
        $new->timecreated   = $record->timecreated;
        $new->strings       = $record->strings;
        $new->components    = explode('/', trim($record->components, '/'));
        $new->languages     = explode('/', trim($record->languages, '/'));
        $new->owner         = $owner;

        if ($record->hash === 'xxxxautosaveuser'.$new->owner->id) {
            $new->isautosave = true;
        } else {
            $new->isautosave = false;
        }

        return $new;
    }

    /**
     * Constructor is not public, use one of factory methods above
     */
    protected function __construct() {
        // does nothing
    }

    /**
     * Register a new action that can be done with the stash
     *
     * @param string $id action identifier
     * @param moodle_url $url action handler
     * @param string $label action name
     */
    public function add_action($id, moodle_url $url, $label) {

        $action             = new stdClass();
        $action->id         = $id;
        $action->url        = $url;
        $action->label      = $label;
        $this->actions[]    = $action;
    }

    /**
     * Get the list of actions attached to this stash
     *
     * @return array of stdClasses with $url and $label properties
     */
    public function get_actions() {
        return $this->actions;
    }
}

/**
 * Represents renderable contribution infor
 */
class local_amos_contribution implements renderable {

    const STATE_NEW         = 0;
    const STATE_REVIEW      = 10;
    const STATE_REJECTED    = 20;
    const STATE_ACCEPTED    = 30;

    /** @var stdClass */
    public $info;
    /** @var stdClass */
    public $author;
    /** @var stdClss */
    public $assignee;
    /** @var string */
    public $language;
    /** @var string */
    public $components;
    /** @var int number of strings */
    public $strings;
    /** @var int number of strings after rebase */
    public $stringsreb;

    public function __construct(stdClass $info, stdClass $author=null, stdClass $assignee=null) {
        global $DB;

        $this->info = $info;

        if (empty($author)) {
            $this->author = $DB->get_record('user', array('id' => $info->authorid));
        } else {
            $this->author = $author;
        }

        if (empty($assignee) and !empty($info->assignee)) {
            $this->assignee = $DB->get_record('user', array('id' => $info->assignee));
        } else {
            $this->assignee = $assignee;
        }
    }
}

/**
 * Returns the list of standard components
 *
 * @return array (string)version => (string)legacyname => (string)frankenstylename
 */
function local_amos_standard_plugins() {
    global $CFG;
    static $list = null;

    if (is_null($list)) {
        $xml  = simplexml_load_file($CFG->dirroot.'/local/amos/plugins.xml');
        foreach ($xml->moodle as $moodle) {
            $version = (string)$moodle['version'];
            $list[$version] = array();
            $list[$version]['moodle'] = 'core';
            foreach ($moodle->plugins as $plugins) {
                $type = (string)$plugins['type'];
                foreach ($plugins as $plugin) {
                    $name = (string)$plugin;
                    if ($type == 'core' or $type == 'mod') {
                        $list[$version][$name] = "{$type}_{$name}";
                    } else {
                        $list[$version]["{$type}_{$name}"] = "{$type}_{$name}";
                    }
                }
            }
        }
    }

    return $list;
}

/**
 * Returns the list of app components
 *
 * @return array (string)frankenstylename
 */
function local_amos_app_plugins() {
    global $DB;

    static $list = null;

    if (is_null($list)) {
        $components = $DB->get_fieldset_select('amos_app_strings', 'DISTINCT component', "");
        $list = array_combine($components, $components);
        $list['local_moodlemobileapp'] = 'local_moodlemobileapp';
    }

    return $list;
}

/**
 * Returns the list of app components
 *
 * @return array (string)component/(string)stringid => (string)appid
 */
function local_amos_applist_strings() {
    global $DB;

    static $applist = null;

    if (is_null($applist)) {
        // get the app strings
        $applist = array();
        $rs = $DB->get_records('amos_app_strings');
        foreach ($rs as $s) {
            $applist[$s->component.'/'.$s->stringid] = $s->appid;
        }
    }



    return $applist;
}

/**
 * Returns the options used for {@link importfile_form.php}
 *
 * @return array
 */
function local_amos_importfile_options() {

    $options = array();

    $options['versions'] = array();
    $options['versioncurrent'] = null;
    foreach (mlang_version::list_all() as $version) {
        if ($version->translatable) {
            $options['versions'][$version->code] = $version->label;
        }
    }
    $options['versioncurrent'] = mlang_version::latest_version()->code;
    $options['languages'] = array_merge(array('' => get_string('choosedots')), mlang_tools::list_languages(false));
    $currentlanguage = current_language();
    if ($currentlanguage === 'en') {
        $currentlanguage = 'en_fix';
    }
    $options['languagecurrent'] = $currentlanguage;

    return $options;
}

/**
 * Returns the options used by {@link merge_form.php}
 *
 * @return array
 */
function local_amos_merge_options() {
    global $USER;

    $options = array();

    $options['sourceversions'] = array();
    $options['targetversions'] = array();
    $options['defaultsourceversion'] = null;
    $options['defaulttargetversion'] = null;
    $latestversioncode = mlang_version::latest_version()->code;
    foreach (mlang_version::list_all() as $version) {
        $options['sourceversions'][$version->code] = $version->label;
        if ($version->code != $latestversioncode && $options['defaultsourceversion'] === null) {
            $options['defaultsourceversion'] = $version->code;
        }
        if ($version->translatable) {
            $options['targetversions'][$version->code] = $version->label;
            if ($version->code == $latestversioncode) {
                $options['defaulttargetversion'] = $version->code;
            }
        }
    }

    $langsall     = mlang_tools::list_languages(false);
    $langsallowed = mlang_tools::list_allowed_languages($USER->id);
    if (in_array('X', $langsallowed)) {
        $options['languages'] = array_merge(array('' => get_string('choosedots')), $langsall);
    } else {
        $options['languages'] = array_merge(array('' => get_string('choosedots')), array_intersect_key($langsall, $langsallowed));
    }
    $currentlanguage = current_language();
    if ($currentlanguage === 'en') {
        $currentlanguage = 'en_fix';
    }
    $options['languagecurrent'] = $currentlanguage;

    return $options;
}

/**
 * Returns the options used by {@link diff_form.php}
 *
 * @return array
 */
function local_amos_diff_options() {
    global $USER;

    $options = array();

    $options['versions'] = array();
    $options['defaultversion'] = null;
    $latestversioncode = mlang_version::latest_version()->code;
    foreach (mlang_version::list_all() as $version) {
        $options['versions'][$version->code] = $version->label;
        if ($version->code == $latestversioncode) {
            $options['defaultversion'] = $version->code;
        }
    }

    $langsall = mlang_tools::list_languages(false);
    $options['languages'] = array_merge(array('' => get_string('choosedots')), $langsall);
    $currentlanguage = current_language();
    if ($currentlanguage === 'en') {
        $currentlanguage = 'en_fix';
    }
    $options['languagecurrent'] = $currentlanguage;

    return $options;
}

/**
 * Returns the options used for {@link execute_form.php}
 *
 * @return array
 */
function local_amos_execute_options() {

    $options = array();

    $options['versions'] = array();
    $options['versioncurrent'] = null;
    $latestversioncode = mlang_version::latest_version()->code;
    foreach (mlang_version::list_all() as $version) {
        if ($version->translatable) {
            $options['versions'][$version->code] = $version->label;
            if ($version->code == $latestversioncode) {
                $options['versioncurrent'] = $version->code;
            }
        }
    }

    return $options;
}

/**
 * Returns an array of the changes from $old text to the $new one
 *
 * This is just slightly customized version of Paul's Simple Diff Algorithm.
 * Given two arrays of chunks (words), the function returns an array of the changes
 * leading from $old to $new.
 *
 * @author Paul Butler
 * @copyright (C) Paul Butler 2007 <http://www.paulbutler.org/>
 * @license May be used and distributed under the zlib/libpng license
 * @link https://github.com/paulgb/simplediff
 * @version 26f97a48598d7b306ae9
 * @param array $old array of words
 * @param array $new array of words
 * @return array
 */
function local_amos_simplediff(array $old, array $new) {

    $maxlen = 0;

    foreach ($old as $oindex => $ovalue) {
        $nkeys = array_keys($new, $ovalue);
        foreach ($nkeys as $nindex){
            $matrix[$oindex][$nindex] = isset($matrix[$oindex - 1][$nindex - 1]) ?
                $matrix[$oindex - 1][$nindex - 1] + 1 : 1;
            if ($matrix[$oindex][$nindex] > $maxlen) {
                $maxlen = $matrix[$oindex][$nindex];
                $omax   = $oindex + 1 - $maxlen;
                $nmax   = $nindex + 1 - $maxlen;
            }
        }
    }
    if ($maxlen == 0) {
        return array(array('d' => $old, 'i' => $new));
    }
    return array_merge(
        local_amos_simplediff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
        array_slice($new, $nmax, $maxlen),
        local_amos_simplediff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen)));
}

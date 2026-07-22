<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AMOS local library.
 *
 * @package     local_amos
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/amos/mlanglib.php');

define('AMOS_USER_MAINTAINER',  0);
define('AMOS_USER_CONTRIBUTOR', 1);

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
    /** @var int timestamp of when the stash was modified */
    public $timemodified;
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
     * Factory method using an instance if {@see mlang_stash} as a data source
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
        $new->timemodified  = $stash->timemodified;

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
        $new->timemodified  = $record->timemodified;
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

    /** Newly submitted contribution. */
    const STATE_NEW = 0;

    /** Contribution under review. */
    const STATE_REVIEW = 10;

    /** Rejected contribution. */
    const STATE_REJECTED = 20;

    /** Accepted submission. */
    const STATE_ACCEPTED = 30;

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

    /**
     * Constructor.
     *
     * @param stdClass $info Contribution data.
     * @param stdClass $author Contributor user record, if known.
     * @param stdClass $assignee Assignee user record, if known.
     */
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
 * Returns the list of workplace components
 *
 * @return array (string)componentname
 */
function local_amos_workplace_plugins() {
    $components = get_config('local_amos', 'workplacecomponents');
    return explode(',', $components);
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
        // Get the app strings.
        $applist = array();
        $rs = $DB->get_records('amos_app_strings');
        foreach ($rs as $s) {
            $applist[$s->component.'/'.$s->stringid] = $s->appid;
        }
    }

    return $applist;
}

/**
 * Returns the options used for {@see local_amos_importfile_form}
 *
 * @return array
 */
function local_amos_importfile_options() {

    $options = array();

    $options['versions'] = ['auto' => get_string('versionauto', 'local_amos')];
    $options['versioncurrent'] = 'auto';
    foreach (array_reverse(mlang_version::list_all(), true) as $version) {
        if ($version->translatable) {
            $options['versions'][$version->code] = $version->label;
        }
    }
    $options['languages'] = array_merge(array('' => get_string('choosedots')), mlang_tools::list_languages(false));
    $currentlanguage = current_language();
    if ($currentlanguage === 'en') {
        $currentlanguage = 'en_fix';
    }
    $options['languagecurrent'] = $currentlanguage;

    return $options;
}

/**
 * Stage translated strings at the auto-detected version of their English source.
 *
 * For each string in $parsed, the most recent non-deleted English version is
 * looked up via {@see mlang_component::from_snapshot()}. Strings that have no
 * English counterpart are silently skipped. The translated strings are added to
 * $stage grouped by their target version.
 *
 * @param mlang_stage $stage Stage to add the versioned components into.
 * @param mlang_component $parsed Component holding the parsed translated strings.
 *     Its name and lang properties determine which component and language are staged.
 */
function local_amos_importfile_stage_auto(mlang_stage $stage, mlang_component $parsed): void {

    // Load English strings with full info so that $extra->since is populated.
    // $deleted=false ensures strings currently deleted in English are excluded.
    $encomponent = mlang_component::from_snapshot($parsed->name, 'en', mlang_version::latest_version(),
        null, false, true);

    // Group translated strings by their English source version code.
    $byversion = [];
    foreach ($parsed->get_string_keys() as $strname) {
        $enstr = $encomponent->get_string($strname);
        if ($enstr === null) {
            // Not present in current English; skip.
            continue;
        }
        $byversion[$enstr->extra->since][] = $strname;
    }

    // Add one mlang_component per version group to the stage.
    foreach ($byversion as $vcode => $strnames) {
        $mver = mlang_version::by_code($vcode);
        if (!$mver->translatable) {
            continue;
        }
        $component = new mlang_component($parsed->name, $parsed->lang, $mver);
        foreach ($strnames as $strname) {
            $str = $parsed->get_string($strname);
            $component->add_string(new mlang_string($str->id, $str->text, $str->timemodified, $str->deleted));
        }
        if ($component->has_string()) {
            $stage->add($component, true);
            $component->clear();
        }
    }
}

/**
 * Returns the options used for {@see local_amos_execute_form}
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
 * @param array $old array of words
 * @param array $new array of words
 * @return array
 */
function local_amos_simplediff(array $old, array $new) {

    $maxlen = 0;

    foreach ($old as $oindex => $ovalue) {
        $nkeys = array_keys($new, $ovalue);
        foreach ($nkeys as $nindex) {
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

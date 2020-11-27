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

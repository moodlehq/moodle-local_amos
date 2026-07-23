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

use local_amos\local\amos_component;
use local_amos\local\amos_stage;
use local_amos\local\amos_string;
use local_amos\local\amos_tools;
use local_amos\local\amos_version;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/amos/mlanglib.php');

define('AMOS_USER_MAINTAINER', 0);
define('AMOS_USER_CONTRIBUTOR', 1);

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
        $applist = [];
        $rs = $DB->get_records('amos_app_strings');
        foreach ($rs as $s) {
            $applist[$s->component . '/' . $s->stringid] = $s->appid;
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

    $options = [];

    $options['versions'] = ['auto' => get_string('versionauto', 'local_amos')];
    $options['versioncurrent'] = 'auto';
    foreach (array_reverse(amos_version::list_all(), true) as $version) {
        if ($version->translatable) {
            $options['versions'][$version->code] = $version->label;
        }
    }
    $options['languages'] = array_merge(['' => get_string('choosedots')], amos_tools::list_languages(false));
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
 * looked up via {@see amos_component::from_snapshot()}. Strings that have no
 * English counterpart are silently skipped. The translated strings are added to
 * $stage grouped by their target version.
 *
 * @param amos_stage $stage Stage to add the versioned components into.
 * @param amos_component $parsed Component holding the parsed translated strings.
 *     Its name and lang properties determine which component and language are staged.
 */
function local_amos_importfile_stage_auto(amos_stage $stage, amos_component $parsed): void {

    // Load English strings with full info so that $extra->since is populated.
    // $deleted=false ensures strings currently deleted in English are excluded.
    $encomponent = amos_component::from_snapshot(
        $parsed->name,
        'en',
        amos_version::latest_version(),
        null,
        false,
        true
    );

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

    // Add one amos_component per version group to the stage.
    foreach ($byversion as $vcode => $strnames) {
        $mver = amos_version::by_code($vcode);
        if (!$mver->translatable) {
            continue;
        }
        $component = new amos_component($parsed->name, $parsed->lang, $mver);
        foreach ($strnames as $strname) {
            $str = $parsed->get_string($strname);
            $component->add_string(new amos_string($str->id, $str->text, $str->timemodified, $str->deleted));
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

    $options = [];

    $options['versions'] = [];
    $options['versioncurrent'] = null;
    $latestversioncode = amos_version::latest_version()->code;
    foreach (amos_version::list_all() as $version) {
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
        return [['d' => $old, 'i' => $new]];
    }
    return array_merge(
        local_amos_simplediff(array_slice($old, 0, $omax), array_slice($new, 0, $nmax)),
        array_slice($new, $nmax, $maxlen),
        local_amos_simplediff(array_slice($old, $omax + $maxlen), array_slice($new, $nmax + $maxlen))
    );
}

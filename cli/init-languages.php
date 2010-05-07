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
 * Populate the table amos_languages with the snapshot data of langconfig
 *
 * This script was used for initial filling up of amos_languages table from
 * the data defined in langconfig component at MOODLE_20_STABLE branch.
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

$version = mlang_version::by_code(mlang_version::MOODLE_20); // Moodle version to use
$tree = mlang_tools::components_tree(array('branch' => $version->code, 'component' => 'langconfig'));
$tree = $tree[$version->code];
$records = array();

// firstly get the languages without parentlanguage
foreach (array_keys($tree) as $lang) {
    $component = mlang_component::from_snapshot('langconfig', $lang, $version);
    $record = new stdclass();
    $record->lang = $lang;
    foreach ($component->get_iterator() as $string) {
        $record->{$string->id} = $string->text;
    }
    if (!empty($record->parentlanguage) && (($record->parentlanguage == 'en') || ($record->parentlanguage == 'en_utf8'))) {
        unset($record->parentlanguage);
    }
    if (empty($record->parentlanguage)) {
        $records[] = $record;
    }
    $component->clear();
}

// then add languages with parentlanguage set
foreach (array_keys($tree) as $lang) {
    $component = mlang_component::from_snapshot('langconfig', $lang, $version);
    $record = new stdclass();
    $record->lang = $lang;
    foreach ($component->get_iterator() as $string) {
        $record->{$string->id} = $string->text;
    }
    if (!empty($record->parentlanguage) && ($record->parentlanguage != 'en')) {
        $records[] = $record;
    }
    $component->clear();
}

// and finally insert the records if they are not there yet
foreach ($records as $record) {
    if (!empty($record->firstdayofweek)) {
        $record->firstdayofweek = (int)$record->firstdayofweek;
    }
    if (!$DB->record_exists('amos_languages', array('lang' => $record->lang))) {
        $DB->insert_record('amos_languages', $record);
    }
}
echo "DONE\n";

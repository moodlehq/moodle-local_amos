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
 * Populate a local AMOS repository with fake data
 *
 * This is intended for development purposes only.
 *
 * @package     local_amos
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', 1);

require_once(dirname(__FILE__).'/../../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/amos/cli/config.php');
require_once($CFG->dirroot.'/local/amos/mlanglib.php');
require_once($CFG->dirroot.'/local/amos/locallib.php');

/**
 * Executes the given AMOS CLI utility
 *
 * @param string $command command to run
 */
function cli_exec($command) {
    global $CFG;
    system('/usr/bin/php '.$CFG->dirroot.'/local/amos/cli/'.$command);
}

$whoami = shell_exec('whoami');
$whoami = trim($whoami);
if ($whoami !== 'apache') {
    cli_problem($whoami);
    cli_error('You better should execute this as: sudo -u apache php makefake.php');
}

// Check that the AMOS tables are empty.
$dbman = $DB->get_manager();

$amosxmldbfile = new xmldb_file($CFG->dirroot.'/local/amos/db/install.xml');
if (!$amosxmldbfile->fileExists() or !$amosxmldbfile->loadXMLStructure()) {
    cli_error('Error: unable to read AMOS install.xml');
}
$structure = $amosxmldbfile->getStructure();
$tables = $structure->getTables();
$tablenames = array();

foreach ($tables as $table) {
    $tablenames[] = $table->getName();
}

foreach ($tablenames as $tablename) {
    if ($DB->count_records($tablename)) {
        cli_problem('Error: some existing records found in '.$tablename);
    }
}

// Register English language at 2.3 branch.
$component = new mlang_component('langconfig', 'en', mlang_version::by_branch('MOODLE_23_STABLE'));
$component->add_string(new mlang_string('thislanguage', 'English'));
$component->add_string(new mlang_string('thislanguageint', 'English'));
$stage = new mlang_stage();
$stage->add($component);
$stage->commit('Registering the English language', array('source' => 'bot'), true);
$component->clear();

// Register Czech language at 2.3 branch.
$component = new mlang_component('langconfig', 'cs', mlang_version::by_branch('MOODLE_23_STABLE'));
$component->add_string(new mlang_string('thislanguage', 'Čeština'));
$component->add_string(new mlang_string('thislanguageint', 'Czech'));
$stage = new mlang_stage();
$stage->add($component);
$stage->commit('Registering the Czech language', array('source' => 'bot'), true);
$component->clear();

// Import English core component into 2.3.
cli_exec('import-strings.php --version=MOODLE_23_STABLE --message="Core strings - initial import" ' .
    $CFG->dirroot.'/lang/en/moodle.php');

// Import English Workshop module into 2.3.
cli_exec('import-strings.php --version=MOODLE_23_STABLE --message="Workshop strings - initial import" ' .
    $CFG->dirroot.'/mod/workshop/lang/en/workshop.php');

// Fork 2.4 branch.
cli_exec('make-branch.php --from=MOODLE_23_STABLE --to=MOODLE_24_STABLE');

// Import English Book module into 2.4.
cli_exec('import-strings.php --version=MOODLE_24_STABLE --message="Book strings - initial import" ' .
    $CFG->dirroot.'/mod/book/lang/en/book.php');

// Register a contrib component into 2.3.
$component = new mlang_component('stampcoll', 'en', mlang_version::by_branch('MOODLE_23_STABLE'));
$component->add_string(new mlang_string('pluginname', 'Stamp colletion'));
$component->add_string(new mlang_string('stamp', 'Stamp'));
$stage = new mlang_stage();
$stage->add($component);
$stage->commit('Registering the Stamp collection strings', array('source' => 'bot'), true);
$component->clear();

// Provide a translation of Stamp collection in 2.3.
$component = new mlang_component('stampcoll', 'cs', mlang_version::by_branch('MOODLE_23_STABLE'));
$component->add_string(new mlang_string('pluginname', 'Sbírka razítek'));
$stage = new mlang_stage();
$stage->add($component);
$stage->commit('Translating the Stamp collection strings', array('source' => 'bot'), true);
$component->clear();

// Register a component at 2.4 and delete it.
$component = new mlang_component('delete', 'en', mlang_version::by_branch('MOODLE_24_STABLE'));
$component->add_string(new mlang_string('invalid', 'Just experimenting'));
$stage = new mlang_stage();
$stage->add($component);
$stage->commit('Registering a to-be-deleted component string', array('source' => 'bot'), true);
$component->clear();
sleep(2);
$component = new mlang_component('delete', 'en', mlang_version::by_branch('MOODLE_24_STABLE'));
$component->add_string(new mlang_string('invalid', 'Just experimenting', null, 1));
$stage = new mlang_stage();
$stage->add($component);
$stage->commit('Deleting all strings from the component', array('source' => 'bot'), true);
$component->clear();

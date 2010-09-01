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
 * AMOS upgrade scripts
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_local_amos_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();
    $result = true;

    if ($oldversion < 2010090103) {
        $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/local/amos/db/install.xml', 'amos_stashes');
        upgrade_plugin_savepoint(true, 2010090103, 'local', 'amos');
    }

    if ($oldversion < 2010090107) {
        $dbman->install_one_table_from_xmldb_file($CFG->dirroot.'/local/amos/db/install.xml', 'amos_hidden_requests');
        upgrade_plugin_savepoint(true, 2010090107, 'local', 'amos');
    }

    return $result;
}

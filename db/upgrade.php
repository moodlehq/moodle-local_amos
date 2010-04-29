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

    if ($result && $oldversion < 2010042900) {

    /// Define index ix_timemodified (not unique) to be added to amos_repository
        $table = new xmldb_table('amos_repository');
        $index = new xmldb_index('ix_timemodified', XMLDB_INDEX_NOTUNIQUE, array('timemodified'));

    /// Conditionally launch add index ix_timemodified
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

    /// amos savepoint reached
        upgrade_plugin_savepoint($result, 2010042900, 'local', 'amos');
    }

    return $result;
}

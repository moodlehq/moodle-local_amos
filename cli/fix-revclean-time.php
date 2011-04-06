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
 * Fix the timemodified timestamp of the strings removed by reverse cleanup
 *
 * This should be executed just once to fix the database records.
 *
 * @package    local
 * @subpackage amos
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

$sqls = "SELECT r.id, c.timecommitted";
$sqlc = "SELECT COUNT(*)";
$sql  = "  FROM {amos_repository} r
           JOIN {amos_commits} c ON r.commitid = c.id
          WHERE c.source = 'revclean'
                AND r.timemodified <> c.timecommitted";

$count = $DB->count_records_sql($sqlc . $sql);
$rs    = $DB->get_recordset_sql($sqls . $sql);

$i = 0;
foreach ($rs as $record) {
    $DB->set_field('amos_repository', 'timemodified', $record->timecommitted, array('id' => $record->id));
    $i++;
    echo "\r$i/$count";
}
$rs->close();

echo "\n";

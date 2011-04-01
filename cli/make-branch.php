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
 * Create a new branch of strings in the AMOS repository
 *
 * @package    local
 * @subpackage amos
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(array('from'=>false, 'to'=>false, 'help'=>false), array('h'=>'help'));

if ($options['help'] or !$options['from'] or !$options['to']) {
    echo 'Usage: '.basename(__FILE__).' --from=MOODLE_XX_STABLE --to=MOODLE_YY_STABLE' . PHP_EOL;
    exit(1);
}

$fromversion = mlang_version::by_branch($options['from']);
$toversion   = mlang_version::by_branch($options['to']);

if (is_null($fromversion) or is_null($toversion)) {
    echo 'Unknown branch' . PHP_EOL;
    exit(2);
}

// Make sure that this is not executed by mistake
if ($DB->record_exists('amos_repository', array('branch' => $toversion->code))) {
    echo 'The target branch already exists' . PHP_EOL;
    exit(3);
}

// Let us get the list of commits that change strings on the given branch
$sql = "SELECT DISTINCT commitid
          FROM {amos_repository}
         WHERE branch = ?
      ORDER BY commitid";

$rs = $DB->get_recordset_sql($sql, array($fromversion->code));

foreach ($rs as $record) {
    $commitid = $record->commitid;
    echo 'cloning changes introduced by commit ' . $commitid . ' ... ';

    // all changes introduced by the commit at the given branch
    $changes = $DB->get_records('amos_repository', array('commitid' => $commitid, 'branch' => $fromversion->code), 'id');

    // clone every string change onto the new branch
    $transaction = $DB->start_delegated_transaction();
    foreach ($changes as $change) {
        unset($change->id);
        $change->branch = $toversion->code;

        $DB->insert_record('amos_repository', $change);
    }
    $transaction->allow_commit();

    echo 'done' . PHP_EOL;
}

$rs->close();

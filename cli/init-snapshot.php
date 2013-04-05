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
 * Initialize the mdl_amos_snapshot table
 *
 * Usage:
 *
 *  $ php cli/init-snapshot.php 2> snapshot.log
 *
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

fwrite(STDOUT, "Counting records to process ... ");
$count = $DB->count_records('amos_repository');
fwrite(STDOUT, $count.PHP_EOL);

fwrite(STDOUT, "Loading recordset ... ");
$rs = $DB->get_recordset('amos_repository', null, 'id');    // Ordering is important here
fwrite(STDOUT, "done".PHP_EOL);

fwrite(STDOUT, "Processing ... ".PHP_EOL);
$progress = '0/'.$count.' (0%)';
fwrite(STDOUT, "$progress\r");

$i = 0;
foreach ($rs as $record) {
    $i++;
    $current = $DB->get_record_sql(
        "SELECT s.id AS snapshotid, r.branch, r.component, r.lang, r.stringid,
                r.id AS repoid, r.timemodified
           FROM {amos_snapshot} s
           JOIN {amos_repository} r ON r.id = s.repoid
          WHERE s.branch = :branch
                AND s.component = :component
                AND s.lang = :lang
                AND s.stringid = :stringid",
        array(
            'branch' => $record->branch,
            'component' => $record->component,
            'lang' => $record->lang,
            'stringid' => $record->stringid
        )
    );

    if ($current === false) {
        $DB->insert_record('amos_snapshot', (object)array(
            'branch' => $record->branch,
            'component' => $record->component,
            'lang' => $record->lang,
            'stringid' => $record->stringid,
            'repoid' => $record->id));

    } else {
        if ($current->branch != $record->branch or $current->component !== $record->component
                or $current->lang !== $record->lang or $current->stringid !== $record->stringid) {
            fwrite(STDERR, PHP_EOL."ERROR: Reference mismatch: ".$current->snapshotid.PHP_EOL);
            exit(1);
        }

        if ($current->timemodified <= $record->timemodified) {
            fwrite(STDERR, $record->id." is newer than ".$current->repoid.PHP_EOL);
            $DB->set_field('amos_snapshot', 'repoid', $record->id, array('id' => $current->snapshotid));
        }
    }

    $progress = $i.'/'.$count.' ('.sprintf('%d%%', 100*$i/$count).')';
    fwrite(STDOUT, "$progress\r");
}

fwrite(STDOUT, PHP_EOL);

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
 * Deletes all en_fix strings that are merged into the en pack.
 *
 * @package   local_amos
 * @copyright 2013 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir  . '/clilib.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

list($options, $unrecognized) = cli_get_params(array('execute' => false));

fputs(STDOUT, "*****************************************\n");
fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " ENFIX CLEANUP JOB STARTED\n");

// Get an information about existing strings in the en_fix
$sql = "SELECT branch,lang,component,COUNT(stringid) AS numofstrings
          FROM {amos_repository}
         WHERE deleted=0
           AND lang='en_fix'
      GROUP BY branch,lang,component
      ORDER BY branch,lang,component";
$rs = $DB->get_recordset_sql($sql);
$tree = array();    // [branch][language][component] => numofstrings
foreach ($rs as $record) {
    $tree[$record->branch][$record->lang][$record->component] = $record->numofstrings;
}
$rs->close();

$stage = new mlang_stage();

foreach ($tree as $vercode => $languages) {
    $version = mlang_version::by_code($vercode);
    foreach ($languages as $langcode => $components) {
        if ($langcode !== 'en_fix') {
            throw new coding_exception('Unexpected language');
        }
        foreach ($components as $componentname => $unused) {
            $en = mlang_component::from_snapshot($componentname, 'en', $version);
            $enfix = mlang_component::from_snapshot($componentname, $langcode, $version);
            $enfix->intersect($en);
            $removed = $enfix->complement($en);
            if ($removed) {

                if ($options['execute']) {
                    $action = 'removing';
                } else {
                    $action = 'would remove';
                }

                fputs(STDERR, $action.' '.$removed.' string(s) from '.$componentname.' '.$version->label.PHP_EOL);

                if ($options['execute']) {
                    $stage->add($enfix);
                    $stage->rebase(null, true);
                    $msg = 'Clean-up strings that were merged into the English pack';
                    $stage->commit($msg, array('source' => 'bot', 'userinfo' => 'AMOS-bot <amos@moodle.org>'), true);
                } else {
                    $stage->clear();
                }
            } else {
                fputs(STDERR, 'nothing to do in '.$componentname.' '.$version->label.PHP_EOL);
            }
            $en->clear();
            $enfix->clear();
        }
    }
}

fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " ENFIX CLEANUP JOB DONE\n");

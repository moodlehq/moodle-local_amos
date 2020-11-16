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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Convert the strings storage from the legacy amos_repository table.
 *
 * @package     local_amos
 * @copyright   2018 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once('../../../config.php');
require_once($CFG->libdir.'/clilib.php');

$chunk = 0;
$size = 10000;
$done = false;

$maxid = $DB->get_field_select('amos_commits', 'MAX(id)', '1=1');

while (!$done) {
    $sql = "SELECT s.commitid, s.lang, s.id AS stringid, s.branch, s.component, s.stringid AS strname,
                   s.timemodified, s.timeupdated, s.deleted, t.text
              FROM {amos_commits} c
              JOIN {amos_repository} s ON s.commitid = c.id
              JOIN {amos_texts} t ON s.textid = t.id
             WHERE c.id >= $chunk * $size AND c.id < ($chunk + 1) * $size
          ORDER BY c.id, CASE WHEN s.lang='en' THEN 0 ELSE 1 END, s.lang, s.component, s.stringid, s.branch, s.timemodified, s.id";

    $rs = $DB->get_recordset_sql($sql);

    if (!$rs->valid()) {
        printf("no commits in chunk %d\n", $chunk);
        $rs->close();
        $chunk++;
        continue;
    }

    foreach ($rs as $r) {
        if ($r->commitid == $maxid) {
            $done = true;
        }
        printf("%d %% / commitid %d / lang %s / string id %d [%s, %s] / branch %s / timemodified %d\n",
            floor(100 * $r->commitid / $maxid),
            $r->commitid,
            $r->lang,
            $r->stringid,
            $r->component,
            $r->strname,
            $r->branch,
            $r->timemodified
        );

        $timelineparams = [
            'component' => $r->component,
            'strname' => $r->strname,
        ];

        if ($r->lang === 'en') {
            $table = 'amos_strings';
            $wherelang = '';
        } else {
            $table = 'amos_translations';
            $wherelang = " AND lang = :lang";
            $timelineparams['lang'] = $r->lang;
        }

        // Check if the string matches the latest one in the new storage.
        $timeline = "SELECT id, strtext, since, timemodified, commitid
                       FROM {".$table."}
                      WHERE component = :component AND strname = :strname $wherelang
                   ORDER BY since DESC, timemodified DESC";

        $revs = $DB->get_records_sql($timeline, $timelineparams);

        // In the new storage, deletion is indicated by NULL value.
        if ($r->deleted) {
            $r->text = null;
            unset($r->deleted);
        }

        // In the new storage, branches read like 38, 39, 310, 311, 400 and not 3800 or 4000 like before.
        $r->branch = $r->branch / 100;

        // What used to be 4.0 string should be mapped to 3.10.
        if ($r->branch == 40) {
            $r->branch = 310;
        }

        if (empty($revs)) {
            // If it's a new string, register it.
            printf(" - registering a new string ... id %d\n", amos_convert_repository_insert($table, $r));

        } else {
            // Check if we already have the record registered.
            foreach ($revs as $rev) {
                $timematch = ($r->timemodified == $rev->timemodified);
                $valuematch = ($r->text === $rev->strtext);
                $commitmatch = ($r->commitid == $rev->commitid);
                $branchsameorlater = ($r->branch >= $rev->since);

                if ($timematch && $valuematch && $commitmatch && $branchsameorlater) {
                    printf(" - identical to already registered string id %d\n", $rev->id);
                    continue 2;
                }
            }

            $latest = reset($revs);
            $timesameorlater = ($r->timemodified >= $latest->timemodified);
            $valuematch = ($r->text === $latest->strtext);
            $commitmatch = ($r->commitid == $latest->commitid);
            $branchsameorlater = ($r->branch >= $latest->since);

            if ($timesameorlater && !$commitmatch && $valuematch && $branchsameorlater) {
                // E.g. commitid 19705 - introducing same strings but in later commit. This is probably a result of some missing
                // string removal records or another data inconsistency in the amos_repository. No need to record it, let's
                // keep just real changes.
                printf(" - same value as previously registered string id %d\n", $latest->id);
                continue;

            } else if ($timesameorlater && $commitmatch && $valuematch && $branchsameorlater) {
                // E.g. commit 28626, string curltimeoutkbitrate - has different time in 2.0 from later. Weird, but no need
                // to register an update.
                printf(" - same value as string id %d\n", $latest->id);
                continue;

            } else if ($timesameorlater && !$commitmatch && !$valuematch && $branchsameorlater) {
                printf(" - registering an update ... id %d\n", amos_convert_repository_insert($table, $r));

            } else if ($timesameorlater && $commitmatch && !$valuematch && $branchsameorlater) {
                // E.g. commit 28626, string validnumberformats_help has value change between 20 and 21 - whitespace
                // removed, but still the same commit.
                printf(" - registering an update ... id %d\n", amos_convert_repository_insert($table, $r));

            } else if ($timesameorlater && !$commitmatch && !$branchsameorlater) {
                // The string was backported into an older stable branch.
                printf(" - registering a backport ... id %d\n", amos_convert_repository_insert($table, $r));

            } else if (!$timesameorlater && !$valuematch && $branchsameorlater) {
                // Later commit introduces earlier change. We do not need it, but let's keep it to see the evolution of the string.
                printf(" - registering an earlier version ... id %d\n", amos_convert_repository_insert($table, $r));

            } else if (!$timesameorlater && $valuematch && $branchsameorlater) {
                // Later commit has the current string, too. We do not need it and often this is a result of a AMOS hacking.
                // Ignore it.
                printf(" - same value as string id %d\n", $latest->id);
                continue;

            } else if (!$timesameorlater && !$branchsameorlater) {
                // Later commit introduces an earlier version on an earlier branch - record it.
                printf(" - registering an earlier version ... id %d\n", amos_convert_repository_insert($table, $r));

            } else {
                // Ooops, no handling code for this type of commits.
                printf(" timesameorlater %d, valuematch %d, commitmatch %d, branchsameorlater %d\n",
                    $timesameorlater, $valuematch, $commitmatch, $branchsameorlater);

                print_object($latest);
                print_object($r);
                die();
            }
        }
    }

    $rs->close();
    $chunk++;
}

function amos_convert_repository_insert($t, $r) {
    global $DB;

    $s = (object)[
        'component' => $r->component,
        'strname' => $r->strname,
        'strtext' => $r->text,
        'since' => $r->branch,
        'timemodified' => $r->timemodified,
        'commitid' => $r->commitid,
    ];

    if (isset($r->lang)) {
        $s->lang = $r->lang;
    }

    return $DB->insert_record($t, $s);
}

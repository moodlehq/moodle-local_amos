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

require(__DIR__ . '/../../..//config.php');
require_once($CFG->libdir  . '/clilib.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

[$options, $unrecognised] = cli_get_params([
    'verbose' => false,
    'execute' => false,
    'aggresive' => false,
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

fputs(STDOUT, "*****************************************\n");
fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " ENFIX CLEANUP JOB STARTED\n");

$standardcomponents = \local_amos\local\util::standard_components_tree();
$supportedversions = mlang_version::list_all();
$stage = new mlang_stage();

foreach ($supportedversions as $version) {
    foreach (array_keys($standardcomponents[$version->code]) as $componentname) {
        if ($componentname === 'langconfig') {
            continue;
        }
        $enfix = mlang_component::from_snapshot($componentname, 'en_fix', $version);
        $en = mlang_component::from_snapshot($componentname, 'en', $version);
        $enfix->intersect($en);

        if (!$enfix->has_string()) {
            continue;
        }

        $removed = $enfix->complement($en);

        if ($options['aggresive']) {
            foreach ($enfix as $enfixstring) {
                $enstring = $en->get_string($enfixstring->id);
                if ($enstring === null) {
                    fputs(STDERR,
                        'orphaned string ' . $enfixstring->id . ' in ' . $componentname . ' ' . $version->label . PHP_EOL);
                    continue;
                }
                if ($enstring->timemodified > $enfixstring->timemodified) {
                    fputs(STDERR,
                        'string ' . $enfixstring->id . ' outdated in ' . $componentname . ' ' . $version->label . PHP_EOL);
                    $enfix->unlink_string($enfixstring->id);
                    $removed[] = $enfixstring->id;
                }
            }
        }

        if ($removed) {
            if ($options['execute']) {
                $action = 'removing';
            } else {
                $action = 'would remove';
            }

            fputs(STDERR, $action . ' ' . count($removed) . ' string(s) from ' . $componentname . ' ' . $version->label . PHP_EOL);
            fputs(STDERR, '- ' . implode(', ', $removed) . PHP_EOL);

            if ($options['execute']) {
                $stage->add($enfix);
                $stage->rebase(null, true);
                $msg = 'Clean-up strings that were merged into the English pack';
                $stage->commit($msg, array('source' => 'bot', 'userinfo' => 'AMOS-bot <amos@moodle.org>'), true);
            } else {
                $stage->clear();
            }
        } else {
            if ($options['verbose']) {
                fputs(STDERR, 'nothing to do in ' . $componentname . ' ' . $version->label . PHP_EOL);
            }
        }
        $en->clear();
        $enfix->clear();
    }
}

fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " ENFIX CLEANUP JOB DONE\n");

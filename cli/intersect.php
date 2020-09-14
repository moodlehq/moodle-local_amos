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
 * Removes translation of strings that are not present in the English pack
 *
 * In other words, it calls mlang_component::intersect() against the English
 * original for all components in the repository.
 *
 * Usage:
 *
 *  php intersect.php (for dry-run)
 *  php intersect.php --execute (to actually commit the removal)
 *
 * @package   local_amos
 * @copyright 2012 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/renderer.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(array('execute' => false));

fputs(STDOUT, "*****************************************\n");
fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " INTERSECT JOB STARTED\n");

$cliresult = 0;

$tree = mlang_tools::components_tree();
foreach ($tree as $vercode => $languages) {
    if ($vercode <= 19) {
        continue;
    }
    $version = mlang_version::by_code($vercode);
    foreach ($languages['en'] as $componentname => $unused) {
        $english = mlang_component::from_snapshot($componentname, 'en', $version);
        foreach (array_keys($tree[$vercode]) as $otherlang) {
            if ($otherlang == 'en') {
                continue;
            }
            $stage = new mlang_stage();
            $other = mlang_component::from_snapshot($componentname, $otherlang, $version);
            $removed = $other->intersect($english);
            if ($removed) {
                $stage->add($other);
            }
            $other->clear();
            unset($other);

            $stage->rebase(null, true);

            if ($options['execute']) {
                $action = 'removing';
            } else {
                $action = 'would remove';
            }

            foreach ($stage->get_iterator() as $xcomp) {
                foreach ($xcomp->get_iterator() as $xstr) {
                    fputs(STDERR, $action.' '.$xstr->id.' from '.$componentname.' '.$otherlang.' '.$version->label.PHP_EOL);
                    if (!$options['execute']) {
                        $cliresult = 1;
                    }
                }
            }

            if ($options['execute']) {
                $msg = 'Reverse clean-up of strings that do not exist in the English pack';
                $stage->commit($msg, array('source' => 'bot', 'userinfo' => 'AMOS-bot <amos@moodle.org>'), true);
            } else {
                $stage->clear();
            }
        }
        $english->clear();
    }
}

fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " INTERSECT CLEANUP JOB DONE\n");

exit($cliresult);

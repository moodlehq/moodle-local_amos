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
 * Applies {@link mlang_component::clean_texts()} to all components
 *
 * @package   local_amos
 * @copyright 2012 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->libdir . '/clilib.php');

cli_separator();
fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " CLEAN TEXTS JOB STARTED\n");

$stage = new mlang_stage();

$tree = mlang_tools::components_tree();
foreach ($tree as $vercode => $languages) {
    if ($vercode <= 19) {
        continue;
    }
    $version = mlang_version::by_code($vercode);
    foreach ($languages as $langcode => $components) {
        if ($langcode == 'en') {
            continue;
        }
        foreach ($components as $componentname => $ignored) {
            $component = mlang_component::from_snapshot($componentname, $langcode, $version);
            $component->clean_texts();
            $stage->add($component);
            $stage->rebase();
            if ($stage->has_component()) {
                $stage->commit('Cleaning strings debris and format', array('source' => 'bot', 'userinfo' => 'AMOS-bot <amos@moodle.org>'), true);
                fputs(STDOUT, $version->label.' '.$langcode.' '.$componentname.' committed '.date('Y-m-d H:i', time()).PHP_EOL);
            } else {
                fputs(STDOUT, $version->label.' '.$langcode.' '.$componentname.' no change '.date('Y-m-d H:i', time()).PHP_EOL);
            }
            $stage->clear();
            $component->clear();
            unset($component);
        }
    }
}

fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " CLEAN TEXTS JOB DONE\n");

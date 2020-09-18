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
 * CLI utility to backport all translations for all components on all branches.
 *
 * @package     local_amos
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/cli/utilslib.php');

$logger = new amos_cli_logger();

$logger->log('backport', 'Loading list of components ...', amos_cli_logger::LEVEL_DEBUG);

$components = array_keys(mlang_tools::list_components());
$count = count($components);
$i = 1;

foreach ($components as $component) {
    $logger->log('backport', sprintf('%d%% %d/%d backporting %s translations ...',
        floor($i / $count * 100), $i, $count, $component));
    mlang_tools::backport_translations($component);
    $i++;
}

$logger->log('backport', 'Done!');

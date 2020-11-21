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
require_once($CFG->libdir.'/clilib.php');

$usage = "
Automatically backport translations to lower versions if they apply.

A typical use case is when plugin English strings are registered on version X and translated. And then the X - 1 version
is registered. Without backporting, that translations would exist since X only. But we want the translations of
identical strings be backported from X to X -1 automatically.

Backporting does not happen on 'en' and 'en_fix' languages.

It is implicitly executed on committing in AMOS UI and whem importing English strings via external API. This script can
be used to run it explicitly.

Usage:
    php backport.php [--component=<frankenstyle>]

Options:
    --component     Backport translations for the given component only. Defaults to all known components.
";

list($options, $unrecognized) = cli_get_params([
    'component' => null,
    'help' => false,
], [
    'h' => 'help',
]);

if ($options['help'] || !empty($unrecognized)) {
    echo $usage . PHP_EOL;
    exit(1);
}

$logger = new amos_cli_logger();

$logger->log('backport', 'Loading list of components ...', amos_cli_logger::LEVEL_DEBUG);

$components = array_keys(mlang_tools::list_components());

if ($options['component']) {
    $components = array_intersect($components, [$options['component']]);
}

$count = count($components);
$i = 1;

foreach ($components as $component) {
    $logger->log('backport', sprintf('%d%% %d/%d backporting %s translations ...',
        floor($i / $count * 100), $i, $count, $component));
    mlang_tools::backport_translations($component);
    $i++;
}

$logger->log('backport', 'Done!');

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
 * Provides CLI access to {@see mlang_version} class features.
 *
 * @package     local_amos
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

$usage = "Display information about the given version.

Usage:
    $ php mlangversion.php --value=<value> --type=<code|dir|branch> --get=<code|dir|branch|label|translatable|current>

Example:
    $ php mlangversion.php --value=3.10 --type=dir --get=branch
";

[$options, $unrecognised] = cli_get_params([
    'value' => '',
    'type' => '',
    'get' => '',
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(2);
}

if (!in_array($options['type'], ['code', 'dir', 'branch'])) {
    cli_error('Invalid --type option value', 3);
}

if (!in_array($options['get'], ['code', 'dir', 'branch', 'label', 'translatable', 'current'])) {
    cli_error('Invalid --get option value', 3);
}

$methodname = 'by_' . $options['type'];
$propertyname = $options['get'];

cli_writeln(mlang_version::$methodname($options['value'])->$propertyname);

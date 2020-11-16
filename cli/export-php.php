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
 * Exports the most recent version of Moodle strings into Moodle PHP format
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->libdir . '/clilib.php');

$usage = "Export component strings into Moodle PHP language file format.

Usage:
    # php export-php.php --component=<component> --language=<language> --version=<version>
    # php export-php.php [--help|-h]
";

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'component' => null,
    'language' => null,
    'version' => null,
], [
    'h' => 'help'
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(2);
}

if ($options['component'] === null || $options['language'] === null || $options['version'] === null) {
    cli_writeln($usage);
    exit(3);
}

mlang_component::from_snapshot($options['component'], $options['language'], mlang_version::by_code($options['version']))
    ->export_phpfile('php://stdout');

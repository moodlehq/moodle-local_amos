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
 * Generate ZIP language packs for publishing
 *
 * @package     local_amos
 * @subpackage  cli
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/amos/cli/utilslib.php');

$usage = "Generate ZIP language packs for publishing.

Usage:
    $ php export-zip.php [--minver=<minver>] [--maxver=<maxver>]

Options:
    --minver=<minver>   Generate only versions greater or equal than given code.
    --maxver=<maxver>   Generate only versions lower or equal than given code.

If versions are limited, the 

Example:
    $ php export-zip.php --minver=400
";

[$options, $unrecognised] = cli_get_params([
    'minver' => null,
    'maxver' => null,
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(2);
}

$logger = new amos_cli_logger();

$exporter = new amos_export_zip($logger);
$exporter->init($options['minver'], $options['maxver']);
$exporter->rebuild_zip_packages();
//$exporter->rebuild_output_folders();

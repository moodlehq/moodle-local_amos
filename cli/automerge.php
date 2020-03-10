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
 * Automerge components
 *
 * @package     local_amos
 * @subpackage  cli
 * @copyright   2013 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/cli/utilslib.php');
require_once($CFG->libdir.'/clilib.php');

$usage = <<<EOF
Automatically fill missing translations from other branches.

Usage:
    php automerge.php [--component=<frankenstyle>]

Options:
    --component     Automerge just the given component. Defaults to all known components.

EOF;

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

$logger->log('automerge', 'Loading list of components ...', amos_cli_logger::LEVEL_DEBUG);

$sql = "SELECT DISTINCT component
          FROM {amos_snapshot}
         WHERE lang = :lang";

$params = ['lang' => 'en'];

if (!empty($options['component'])) {
    $sql .= " AND component = :component";
    $params['component'] = $options['component'];
}

$components = $DB->get_fieldset_sql($sql, $params);

$count = count($components);
$i = 1;
foreach ($components as $component) {
    $logger->log('automerge', $i.'/'.$count.' automerging '.$component.' ...');
    mlang_tools::auto_merge($component);
    $i++;
}

$logger->log('automerge', 'Done!');

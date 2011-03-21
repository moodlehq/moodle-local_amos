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
 * Imports English strings for a given component from PHP file
 *
 * This is used typically for contributed plugins.
 *
 * @package    local
 * @subpackage amos
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(array(
    'lang'          => 'en',
    'version'       => 'MOODLE_20_STABLE',
    'timemodified'  => null,
    'name'          => null,
    'format'        => 2,
    'message'       => '',
    'userinfo'      => 'David Mudrak <david@moodle.com>',
    'commithash'    => null,
    'help'          => false

    ), array('h' => 'help'));

$usage = 'Usage: '.basename(__FILE__).' --message=\'Commit message\' [--other-options] /path/to/file.php';

if ($options['help'] or empty($options['message']) or empty($unrecognized)) {
    echo $usage . PHP_EOL;
    exit(1);
}

$filepath = $unrecognized[0];
if (!is_readable($filepath)) {
    echo 'File not readable' . PHP_EOL;
    echo $usage . PHP_EOL;
    exit(2);
}

$version = mlang_version::by_branch($options['version']);
if (is_null($version)) {
    echo 'Invalid version' . PHP_EOL;
    exit(3);
}

$component = mlang_component::from_phpfile($filepath, $options['lang'], $version,
        $options['timemodified'], $options['name'], $options['format']);

$stage = new mlang_stage();
$stage->add($component);
$stage->rebase(null, true, $options['timemodified']);

if (!$stage->has_component()) {
    echo 'No strings found (after rebase)' . PHP_EOL;
    exit(4);
}

foreach ($stage->get_iterator() as $component) {
    foreach ($component->get_iterator() as $string) {
        if ($string->deleted) {
            $sign = '-';
        } else {
            $sign = '+';
        }
        echo $sign . ' ' . $string->id . PHP_EOL;
    }
}

echo PHP_EOL;
$continue = cli_input('Continue? [y/n]', 'n', array('y', 'n'));
if ($continue !== 'y') {
    echo 'Import aborted' . PHP_EOL;
    exit(5);
}

$meta = array('source' => 'import', 'userinfo' => $options['userinfo']);
if ($options['commithash']) {
    $meta['commithash'] = $commithash;
}

$stage->commit($options['message'], $meta, true);

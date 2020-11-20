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
 * Cmpare the contents of two unzipped language packs
 *
 * @package     local_amos
 * @subpackage  cli
 * @copyright   2013 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', 1);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

$usage = "Use this script to compare the contents of two unzipped language packs

    $ php compare-packs.php --old=/tmp/old/cs --new=/tmp/new/cs
";

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'old' => '',
    'new' => '',
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

$old = $options['old'];
$new = $options['new'];

if (empty($old) || empty($new)) {
    cli_error($usage);
}

if (!is_dir($old)) {
    cli_error($old . ' is not a directory');
}

if (!is_dir($new)) {
    cli_error($new . ' is not a directory');
}

$oldpack = load_language_pack($old);
$newpack = load_language_pack($new);

if ($diff = array_diff(array_keys($oldpack), array_keys($newpack))) {
    cli_writeln('(+ ) component only in old: ' . implode(', ', $diff));
}

if ($diff = array_diff(array_keys($newpack), array_keys($oldpack))) {
    cli_writeln('( +) components only in new: ' . implode(', ', $diff));
}

foreach (array_intersect(array_keys($newpack), array_keys($oldpack)) as $component) {
    if ($diff = array_diff(array_keys($oldpack[$component]), array_keys($newpack[$component]))) {
        cli_writeln('[ +] strings only in old ' . $component . ': ' . implode(', ', $diff));
    }

    if ($diff = array_diff(array_keys($newpack[$component]), array_keys($oldpack[$component]))) {
        cli_writeln('[+ ] strings only in new ' . $component . ': ' . implode(', ', $diff));
    }

    foreach (array_intersect(array_keys($newpack[$component]), array_keys($oldpack[$component])) as $strname) {
        if ($newpack[$component][$strname] !== $oldpack[$component][$strname]) {
            cli_writeln('[!!] string mismatch in component ' . $component . ': ' . $strname);
        }
    }
}

/**
 * Load all strings from all files in the given language pack path.
 *
 * @param string $path
 * @return array (string)componentname => (string)strname => (string)strtext
 */
function load_language_pack(string $path): array {

    $pack = [];

    foreach (new DirectoryIterator($path) as $file) {
        if ($file->isDot() or $file->isDir()) {
            continue;
        }
        if (substr($file->getFilename(), -4) !== '.php') {
            fputs(STDERR, 'Unexpected file ' . $file->getPathname());
            exit(1);
        }
        $component = mlang_component::name_from_filename($file->getFilename());
        $string = [];
        require($file->getPathname());
        $pack[$component] = $string;
        unset($string);
    }

    return $pack;
}

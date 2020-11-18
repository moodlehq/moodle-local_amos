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
 * @package     local_amos
 * @subpackage  cli
 * @copyright   2013 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/pluginlib.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/amos/mlanglib.php');
require_once($CFG->dirroot.'/local/amos/locallib.php');

$usage = "
Use this script to compare the contents of two unzipped language packs

    $ php compare-packs.php --version=2.6 --master=/tmp/en --slave=/tmp/fr
";

list($options, $unrecognized) = cli_get_params(array('version' => '', 'master' => '', 'slave' => ''));

$version = $options['version'];
$master = $options['master'];
$slave = $options['slave'];

if (empty($version) or empty($master) or empty($slave)) {
    cli_error($usage);
}

if (strpos($version, '.')) {
    $version = mlang_version::by_dir($version);
} else {
    $version = mlang_version::by_code($version);
}

$standard = \local_amos\local\util::standard_components_tree();

if (!isset($standard[$version->code])) {
    cli_error($version->dir . ' not a known version');
}

if (!is_dir($master)) {
    cli_error($master.' is not a directory');
}

if (!is_dir($slave)) {
    cli_error($slave.' is not a directory');
}

$mpack = array();
$spack = array();

foreach (new DirectoryIterator($master) as $file) {
    if ($file->isDot() or $file->isDir()) {
        continue;
    }
    if (substr($file->getFilename(), -4) !== '.php') {
        fputs(STDERR, 'Unexpected file '.$file->getPathname());
        exit(1);
    }
    $component = mlang_component::name_from_filename($file->getFilename());
    $string = array();
    require($file->getPathname());
    $mpack[$component] = array_flip(array_keys($string));
    unset($string);
}

foreach (new DirectoryIterator($slave) as $file) {
    if ($file->isDot() or $file->isDir()) {
        continue;
    }
    if (substr($file->getFilename(), -4) !== '.php') {
        fputs(STDERR, 'Unexpected file '.$file->getPathname());
        exit(1);
    }
    $component = mlang_component::name_from_filename($file->getFilename());
    $string = array();
    require($file->getPathname());
    $spack[$component] = array_flip(array_keys($string));
    unset($string);
}

// Report all slave strings not present in the master pack.
foreach ($spack as $component => $strings) {
    foreach (array_keys($strings) as $string) {
        if (!isset($mpack[$component][$string])) {
            fputs(STDERR, '['.$component.','.$string.'] defined in the slave only'.PHP_EOL);
        }
    }
}

// Report all missing slave strings if the master pack is a standard one.
foreach ($mpack as $component => $strings) {
    if (!isset($standard[$version->code][$component])) {
        continue;
    }
    foreach (array_keys($strings) as $string) {
        if (substr($string, -5) === '_link') {
            continue;
        }
        if (!isset($spack[$component][$string])) {
            fputs(STDERR, '['.$component.','.$string.'] defined in the master only'.PHP_EOL);
        }
    }
}

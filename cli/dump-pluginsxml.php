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
 * Generate part of the plugins.xml file for AMOS.
 *
 * @package     local_amos
 * @subpackage  cli
 * @copyright   2013 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$usage = "
Copy this script into the root of a Moodle X.Y site and execute it like

    $ php dump-pluginsxml.php --version=X.Y
";

define('CLI_SCRIPT', 1);

require(__DIR__.'/config.php');
require_once($CFG->libdir.'/pluginlib.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(array('version' => false));

if ($options['version'] === false) {
    cli_error($usage);
}

$list = array();

// Populate the list of core components first.
foreach (get_core_subsystems() as $name => $x) {
    $list['core'][$name] = 1;
}

// Populate other plugin types.
foreach (get_plugin_types() as $type => $x) {
    $plugins = plugin_manager::standard_plugins_list($type);
    if ($plugins === false) {
        // Not a known plugin type - probably some installed add-on provides subplugins.
        continue;
    }
    foreach ($plugins as $name) {
        $list[$type][$name] = 1;
    }
}

// Generate the XML
fputs(STDOUT, '  <moodle version="'.$options['version'].'">'.PHP_EOL);

foreach ($list as $type => $plugins) {
    fputs(STDOUT, '    <plugins type="'.$type.'">'.PHP_EOL);
    foreach ($plugins as $name => $x) {
        fputs(STDOUT, '      <plugin>'.$name.'</plugin>'.PHP_EOL);
    }
    fputs(STDOUT, '    </plugins>'.PHP_EOL);
}

fputs(STDOUT, '  </moodle>'.PHP_EOL);

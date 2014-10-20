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
 * Merge standard plugins from the given Moodle installation.
 *
 * @package     local_amos
 * @subpackage  cli
 * @copyright   2013 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$usage = "
Merges the information about standard plugins in the given Moodle site
into the AMOS plugins.xml file and prints the new version of the file.

    $ php merge-pluginsxml.php /path/to/moodle/dirroot
";

define('CLI_SCRIPT', 1);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/amos/locallib.php');

// The target file to write info to
$pluginsxml = $CFG->dirroot.'/local/amos/plugins.xml';

// Make sure the target file is writable
if (!is_writable($pluginsxml)) {
    cli_error('Target file '.$pluginsxml.' not writable', 2);
}

// Check that the given path looks like Moodle
list($options, $unrecognized) = cli_get_params(array());

if (empty($unrecognized)) {
    cli_error($usage, 1);
}

$source = reset($unrecognized);

if (!is_dir($source) or !is_readable($source) or !is_readable($source.'/version.php') or !is_readable($source.'/lib/womenslib.php')) {
    cli_error($source.' does not seem to be a Moodle site');
}

// Check that we can write to the source root as we need to put our wire there.
if (!is_writable($source)) {
    cli_error($source.' not writable');
}

// Sniff the source Moodle release and version
unset($version);
unset($release);
unset($branch);
unset($maturity);
include($source.'/version.php');

if (preg_match('/^[0-9]+\.[0-9]+/', $release, $matches)) {
    $majorminor = $matches[0];
} else {
    cli_error('Unable to detect the source Moodle version');
}

// Prepare the wire to be put to the source Moodle
if ($version < 2013111800) {
    // Lower than Moodle 2.6
    $wire = <<<'EOF'
<?php // Temporary file create by AMOS, please remove it.
define('CLI_SCRIPT', 1);
require(__DIR__.'/config.php');
require_once($CFG->libdir.'/pluginlib.php');
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
echo json_encode($list);

EOF;

} else if ($version < 2014101000) {
    // Moodle 2.6 and 2.7 - use core_component::get_core_subsystems()
    $wire = <<<'EOF'
<?php // Temporary file create by AMOS, please remove it.
define('CLI_SCRIPT', 1);
require(__DIR__.'/config.php');
require_once($CFG->libdir.'/pluginlib.php');
$list = array();
// Populate the list of core components first.
foreach (core_component::get_core_subsystems() as $name => $x) {
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
echo json_encode($list);

EOF;

} else {
    // Moodle 2.8 and higher - use class autoloading instead of requiring
    // pluginlib.php manually.
    $wire = <<<'EOF'
<?php // Temporary file create by AMOS, please remove it.
define('CLI_SCRIPT', 1);
require(__DIR__.'/config.php');
$list = array();
// Populate the list of core components first.
foreach (core_component::get_core_subsystems() as $name => $x) {
    $list['core'][$name] = 1;
}
// Populate other plugin types.
foreach (get_plugin_types() as $type => $x) {
    $plugins = core_plugin_manager::standard_plugins_list($type);
    if ($plugins === false) {
        // Not a known plugin type - probably some installed add-on provides subplugins.
        continue;
    }
    foreach ($plugins as $name) {
        $list[$type][$name] = 1;
    }
}
echo json_encode($list);

EOF;
}

// Put the wire to the target Moodle site
$wirefile = tempnam($source, '.amos.tmp');
file_put_contents($wirefile, $wire);

// Execute the wire
$output = shell_exec('php '.$wirefile.' 2>&1');

if (empty($output)) {
    cli_error('Error when executing '.$wirefile, 3);
}

// Remove the wire
unlink($wirefile);

// Try to decode it
$data = json_decode($output, true);

if (empty($data)) {
    cli_error('Unexpected wire output:'.PHP_EOL.PHP_EOL.$output);
}

// Read the current plugins.xml
$list = array();
$xml = simplexml_load_file($pluginsxml);
foreach ($xml->moodle as $moodle) {
    $version = (string)$moodle['version'];
    $list[$version] = array();
    foreach ($moodle->plugins as $plugins) {
        $type = (string)$plugins['type'];
        $list[$version][$type] = array();
        foreach ($plugins as $plugin) {
            $name = (string)$plugin;
            $list[$version][$type][$name] = 1;
        }
    }
}

// Merge the new data, replacing the old one
$list[$majorminor] = $data;

// Populate the contents of the new plugins.xml
$new = '<?xml version="1.0" encoding="utf-8" standalone="yes"?>'.PHP_EOL;
$new .= '<standard_plugins>'.PHP_EOL;
foreach ($list as $version => $plugins) {
    $new .= '  <moodle version="'.$version.'">'.PHP_EOL;
    foreach ($plugins as $type => $names) {
        $new .= '    <plugins type="'.$type.'">'.PHP_EOL;
        foreach (array_keys($names) as $name) {
            $new .= '      <plugin>'.$name.'</plugin>'.PHP_EOL;
        }
        $new .= '    </plugins>'.PHP_EOL;
    }
    $new .= '  </moodle>'.PHP_EOL;
}
$new .= '</standard_plugins>'.PHP_EOL;

file_put_contents($pluginsxml, $new);

echo("Done. Do not forget to run git-diff and check for changes in the plugins.xml file".PHP_EOL);

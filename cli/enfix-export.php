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
 * Exports strings from the en_fix language pack
 *
 * @package   local_amos
 * @copyright 2013 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir  . '/clilib.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

if (!defined('AMOS_EXPORT_ENFIX_DIR')) {
    cli_error('Target directory AMOS_EXPORT_ENFIX_DIR not defined!');
}

remove_dir(AMOS_EXPORT_ENFIX_DIR, true);

$standardcomponents = \local_amos\local\util::standard_components_tree();
$supportedversions = mlang_version::list_supported();

foreach ($supportedversions as $version) {
    foreach (array_keys($standardcomponents[$version->code]) as $componentname) {
        if ($componentname === 'langconfig') {
            continue;
        }
        $component = mlang_component::from_snapshot($componentname, 'en_fix', $version);
        $english = mlang_component::from_snapshot($componentname, 'en', $version);
        $component->intersect($english);
        $english->clear();

        if ($component->has_string()) {
            $file = AMOS_EXPORT_ENFIX_DIR . '/' . $version->code . '/' . $component->name . '.php';

            if (!file_exists(dirname($file))) {
                mkdir(dirname($file), 0755, true);
            }

            echo "$file\n";
            $component->export_phpfile($file);
        }
        $component->clear();
    }
}

echo "DONE\n";

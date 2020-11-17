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
 * AMOS external functions and web services are declared here.
 *
 * @package   local_amos
 * @category  webservice
 * @copyright 2012 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_amos_update_strings_file' => [
        'classname' => '\local_amos\external\api',
        'methodname' => 'update_strings_file',
        'classpath' => '',
        'description' => 'Imports strings from a string file.',
        'type' => 'write',
    ],
    'local_amos_plugin_translation_stats' => [
        'classname' => '\local_amos\external\api',
        'methodname' => 'plugin_translation_stats',
        'classpath' => '',
        'description' => 'Get translation statistics for the given component / plugin.',
        'type' => 'read',
    ],
    'local_amos_stage_translated_string' => [
        'classname' => '\local_amos\external\stage_translated_string',
        'methodname' => 'execute',
        'description' => 'Add a string translation into a persistent AMOS stage.',
        'capabilities' => 'local/amos:stage',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_amos_get_translator_data' => [
        'classname' => '\local_amos\external\get_translator_data',
        'methodname' => 'execute',
        'description' => 'Return data for the translator based on the filter query.',
        'capabilities' => 'local/amos:stage',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_amos_get_string_timeline' => [
        'classname' => '\local_amos\external\get_string_timeline',
        'methodname' => 'execute',
        'description' => 'Return data for the string timeline modal info.',
        'capabilities' => 'local/amos:stage',
        'type' => 'read',
        'ajax' => true,
    ],
];

$services = [
    'AMOS integration with the Moodle Plugins directory' => [
        'functions' => [
            'local_amos_update_strings_file',
            'local_amos_plugin_translation_stats',
        ],
        'requiredcapability' => 'local/amos:importstrings',
        'restrictedusers' => 1,
        'enabled' => 1,
    ],
];

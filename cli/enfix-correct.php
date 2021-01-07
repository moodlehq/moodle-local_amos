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

[$options, $unrecognised] = cli_get_params([
    'execute' => false,
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

$sql = "SELECT component, strname, MAX(since) AS maxsince
          FROM {amos_strings}
      GROUP BY component,strname";

$rs = $DB->get_recordset_sql($sql);

$english = [];

foreach ($rs as $record) {
    $english[$record->component][$record->strname] = $record->maxsince;
}

$rs->close();

$sql = "SELECT component, strname, MAX(since) AS maxsince
          FROM {amos_translations}
         WHERE lang='en_fix'
           AND (component <> 'langconfig')
      GROUP BY component,strname
      ORDER BY component,strname";

$rs = $DB->get_recordset_sql($sql);

$enfix = [];

foreach ($rs as $record) {
    if ($english[$record->component][$record->strname] < $record->maxsince) {
        if ($options['execute']) {
            $DB->delete_records_select('amos_translations', "
                    lang = :lang
                    AND component = :component
                    AND strname = :strname
                    AND since > :since
                ", [
                    'lang' => 'en_fix',
                    'component' => $record->component,
                    'strname' => $record->strname,
                    'since' => $english[$record->component][$record->strname],
                ]
            );

        } else {
            printf('to be deleted: [%s, %s] > %d\n',
                $record->strname,
                $record->component,
                $english[$record->component][$record->strname]
            );
        }
    }
}

$rs->close();

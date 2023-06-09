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
 * Copy the English original as the translation of the selected strings
 *
 * @package   local_amos
 * @copyright 2023 David Mudrak <david@moodle.com>
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
         WHERE " . $DB->sql_like('strname' , ':pattern') . "
      GROUP BY component,strname";

$rs = $DB->get_recordset_sql($sql, [
    'pattern' => '%' . $DB->sql_like_escape('_link')
]);

$english = [];

foreach ($rs as $record) {
    if ($DB->record_exists('amos_strings', [
        'component' => $record->component,
        'strname' => substr($record->strname, 0, -5) . '_help',
    ])) {
        // This seems to be a valid _link string. Let's copy it to all language packs.
        $english[$record->component][$record->maxsince][$record->strname] = true;
    }
}
$rs->close();

// Populate the list of primary language packs to copy to - i.e. all but child packs.
$langs = [];
$withparent = [];

foreach (mlang_tools::list_languages() as $langcode => $langname) {
    $parts = explode('_', $langcode);
    $langs[$langcode] = implode('_', array_slice($parts, 0, -1));
}

foreach ($langs as $code => $parcode) {
    if (array_key_exists($parcode, $langs)) {
        $withparent[$code] = $parcode;
    }
}

$langs = array_keys(array_diff_key($langs, $withparent, ['en' => '']));
sort($langs);

// Put all found strings to the primary language packs.
$stage = new mlang_stage();

foreach ($english as $componentname => $vercodes) {
    foreach ($vercodes as $vercode => $stringnames) {
        foreach ($stringnames as $stringname => $notused) {
            printf("%s\t%d\t%s\n", $componentname, $vercode, $stringname);
            foreach ($langs as $langcode) {
                if ($DB->record_exists('amos_translations', ['lang' => $langcode, 'strname' => $stringname,
                        'component' => $componentname])) {
                    continue;
                }

                $origstring = $DB->get_records_sql(
                    "SELECT *
                       FROM {amos_strings}
                      WHERE strname = :strname
                        AND component = :component
                        AND since = :since
                   ORDER BY timemodified DESC",
                    ['strname' => $stringname, 'component' => $componentname, 'since' => $vercode], 0, 1);

                $origstring = reset($origstring);

                if ($origstring->strtext === null) {
                    continue;
                }

                $component = new mlang_component($componentname, $langcode, mlang_version::by_code($vercode));
                $component->add_string(new mlang_string($stringname, $origstring->strtext));
                $stage->add($component);
                $component->clear();
            }

            if ($options['execute']) {
                $stage->commit('MDLSITE-7236 Automatically fill popup help links', [
                    'source' => 'bot',
                    'userinfo' => 'AMOS-bot <amos@moodle.org>',
                ]);
            }
        }
    }
}

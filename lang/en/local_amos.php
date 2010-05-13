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
 * English strings for AMOS local module
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['amos:commit'] = 'Commit the staged strings into the main repository';
$string['amos:manage'] = 'Manage AMOS portal';
$string['amos:stage'] = 'Use AMOS translation tool and stage the strings';
$string['err_invalidlangcode'] = 'Invalid error code';
$string['foundinfo'] = 'Number of found strings';
$string['foundinfo_help'] = 'Shows the total number of rows in the translator table, number of missing translations and number of missing translations at the current page.';
$string['pluginname'] = 'AMOS';
$string['translatortranslation'] = 'Translation';
$string['translatortranslation_help'] = 'Click the cell to turn it into the input editor. Insert the translation and click outside the cell to stage the translation. The background color of the cell means:

* Green - the string is already translated and you are allowed to modify the translation and commit it.
* Red - the string is not translated and you are allowed to translate it and commit the translation.
* Blue - you have modified the translation and it is now staged. Do not forget to commit the staged translation before you log out!
* No color - even though you can stage the translation, you are not allowed to commit into this languages. You will be only able to export the stage into a file.';
$string['stageactions'] = 'Stage actions';
$string['stageactions_help'] = '* Prune - unstage all translations that you are not allowed to commit. Stage is pruned automatically before it is committed.
* Rebase - unstage all staged strings that do not modify the current translation. Stage is rebased automatically before it is committed.';
$string['stagetranslation'] = 'Translation';
$string['stagetranslation_help'] = 'Displays the staged translation to be committed. The background colot of the cell means:

* Green - you have modified a string or added a missing translation and you are allowed to commit the translation.
* Red - you have modified the translation or added a missing translation but you are not allowed to commit it into the given language.
* No color - the staged translation is the same as the current one and therefore will not be committed.';


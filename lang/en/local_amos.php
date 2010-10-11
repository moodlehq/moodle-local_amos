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
$string['amos:importfile'] = 'Import strings from uploaded file';
$string['amos:manage'] = 'Manage AMOS portal';
$string['amos:stash'] = 'Store the current stage into the persistent stash';
$string['amos:stage'] = 'Use AMOS translation tool and stage the strings';
$string['err_exception'] = 'Error: {$a}';
$string['err_invalidlangcode'] = 'Invalid error code';
$string['err_parser'] = 'Parsing error: {$a}';
$string['foundinfo'] = 'Number of found strings';
$string['foundinfo_help'] = 'Shows the total number of rows in the translator table, number of missing translations and number of missing translations at the current page.';
$string['importfile'] = 'Import translated strings from file';
$string['importfile_help'] = 'If you have your strings translated offline, you can stage them via this form.

* The file must be valid Moodle PHP strings definition file. Look at `/lang/en/` directory of your Moodle installation for examples.
* Name of the file must match the one with English strings definitons for the given component (like `moodle.php`, `assignment.php` or `enrol_manual.php`).

All strings found in the file will be staged for the selected version and language.';
$string['importfile_link'] = 'local/amos/importfile';
$string['language'] = 'Language';
$string['languages'] = 'Languages';
$string['logfiltercommits'] = 'Commit filter';
$string['logfiltersource'] = 'Source';
$string['logfiltersourcegit'] = 'git (git mirror of Moodle source code and 1.x packs)';
$string['logfiltersourceamos'] = 'amos (web based translator)';
$string['logfiltersourcerevclean'] = 'revclean (reverse clean-up process)';
$string['logfiltersourcecommitscript'] = 'commitscript (AMOScript in the commit message)';
$string['logfiltershow'] = 'Show filtered commits and strings';
$string['logfilterusergrp'] = 'Committer';
$string['logfilterusergrpor'] = ' or ';
$string['logfiltercommittedafter'] = 'Committed after';
$string['logfiltercommittedbefore'] = 'Committer before';
$string['logfiltercommitmsg'] = 'Commit message contains';
$string['logfiltercommithash'] = 'git hash';
$string['logfilterstrings'] = 'String filter';
$string['logfilterbranch'] = 'Versions';
$string['logfilterlang'] = 'Languages';
$string['logfiltercomponent'] = 'Components';
$string['logfilterstringid'] = 'String identifier';
$string['markuptodate'] = 'Marking the translation as up-to-date';
$string['markuptodate_help'] = 'AMOS detected that the string may be outdated as the English version was modified after it had been translated. Review the translation. If you find it up-to-date, click the checkbox. Edit it otherwise.';
$string['markuptodate_link'] = 'local/amos/outdatedstrings';
$string['merge'] = 'Merge';
$string['mergestrings'] = 'Merge strings from another branch';
$string['mergestrings_help'] = 'This will pick and stage all strings from the source branch that are not translated yet in the target branch and are used there. You can use this tool to copy a translated string into all other versions of the pack. Only language pack maintainers can use this tool.';
$string['mergestrings_link'] = 'local/amos/merge';
$string['nostringsfound'] = 'No strings found, please modify the filter';
$string['nostringtoimport'] = 'No valid string found in the file. Make sure the file has correct filename and is properly formatted.';
$string['numofcommitsabovelimit'] = 'Found {$a->found} commits matching the commit filter, using {$a->limit} most recent';
$string['numofcommitsunderlimit'] = 'Found {$a->found} commits matching the commit filter';
$string['numofmatchingstrings'] = 'Withing that, {$a->strings} modifications in {$a->commits} commits match the string filter';
$string['pluginname'] = 'AMOS';
$string['pluginclasscore'] = 'Core subsystems';
$string['pluginclassstandard'] = 'Standard plugins';
$string['pluginclassnonstandard'] = 'Non-standard plugins';
$string['translatortranslation'] = 'Translation';
$string['translatortranslation_help'] = 'Click the cell to turn it into the input editor. Insert the translation and click outside the cell to stage the translation. The background color of the cell means:

* Green - the string is already translated and you are allowed to modify the translation and commit it.
* Yellow - the string is committable but may be out-dated. The English version could be modified after the string had been translated.
* Red - the string is not translated and you are allowed to translate it and commit the translation.
* Blue - you have modified the translation and it is now staged. Do not forget to commit the staged translation before you log out!
* No color - even though you can stage the translation, you are not allowed to commit into this languages. You will be only able to export the stage into a file.';
$string['stashactions'] = 'Stash actions';
$string['stashactions_help'] = 'Stash is a snapshot of the current stage. Stashes can be shared with other users and/or they can be offered to the official language pack maintainers for inclusion into the language pack.';
$string['stageactions'] = 'Stage actions';
$string['stageactions_help'] = '* Edit staged strings - modifies the translator filter settings so that only staged translations are displayed.
* Prune non-committable strings - unstage all translations that you are not allowed to commit. Stage is pruned automatically before it is committed.
* Rebase - unstage all translations that either do not modify the current translation or are older than the most recent translation in the repository. Stage is rebased automatically before it is committed.
* Unstage all - clears the stage, all staged translations are lost.';
$string['stagestringsnone'] = 'There are no staged strings';
$string['stagestringssome'] = 'There are {$a->staged} staged strings, {$a->committable} of them can be committed';
$string['stagetranslation'] = 'Translation';
$string['stagetranslation_help'] = 'Displays the staged translation to be committed. The background color of the cell means:

* Green - you have modified a string or added a missing translation and you are allowed to commit the translation.
* Red - you have modified the translation or added a missing translation but you are not allowed to commit it into the given language.
* No color - the staged translation is the same as the current one and therefore will not be committed.';
$string['ownstashactions'] = 'Action';
$string['ownstashactions_help'] = '* Apply - copy the translated strings from the stash into the stage and keep the stash unmodified. If the string is already in the stage, it is overwritten with the stashed one.
* Pop - move the translated strings from the stasg into the stage and drop the stash (that is Apply and Drop).
* Drop - throw away the stashed strings.
* Pull request - make the stash available to the official language maintainers so they can apply your work into their stage, review it and eventually commit.';
$string['placeholder'] = 'Placeholders';
$string['placeholder_help'] = 'Placeholders are special statements like `{$a}` or `{$a->something}` within the string. They are replaced with a value when the string is actually printed.

It is important to copy them exactly as they are in the original string. Do not translate them nor change their left-to-right orientation.';
$string['placeholderwarning'] = 'string contains a placeholder';
$string['requestactions'] = 'Action';
$string['requestactions_help'] = '* Apply - copy the translated strings from the pull request into your stage. If the string is already in the stage, it is overwritten with the stashed one.
* Hide - blocks the pull request so that it is not displayed to you any more.';
$string['sourceversion'] = 'Source version';
$string['targetversion'] = 'Target version';
$string['version'] = 'Version';

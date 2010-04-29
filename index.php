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
 * AMOS home page
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

require_login(SITEID, false);
require_capability('moodle/site:config', $PAGE->context);

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/index.php');
$PAGE->set_title('AMOS Home page');
$PAGE->set_heading('AMOS Home page');

$output = $PAGE->get_renderer('local_amos');

/// Output starts here
echo $output->header();
echo $output->heading('AMOS - Moodle translation portal', 1);
echo $output->heading('Quick instructions', 2);
echo '<ol>
        <li>Go to AMOS &gt; Translator tool in the navigation block tree.</li>
        <li>Use the filter to get the strings you want to work on.</li>
        <li>Missing strings are highlighted in red.</li>
        <li>To edit a current string, click the table cell and it will turn into editable field.</li>
        <li>Once you put the cursor out of the editing field, the translation is put into <em>stage</em>.</li>
        <li>Stage is sort of cache where your work is saved before you commit it into the strings repository.</li>
        <li>Staged translation is highlighted in blue.</li>
        <li>You must commit the staged strings manually. If you log out, all staged strings are lost.</li>
        <li>To commit the staged translations, go to AMOS &gt; Stage tool in the navigation block, fill a commit messagae and press Commit button</li>
        <li>Once the translation is committed, is becomes the part of the repository and is highlighted in green in the translator.</li>
      </ol>';

echo $output->heading('Your privileges', 2);
// TODO this will be displayed according the configuration
echo '<ul>
        <li>You are AMOS manager and can do anything at this tool.</li>
        <li>You can translate any language.</li>
      </ul>';

echo $output->footer();

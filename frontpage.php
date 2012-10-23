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
 * @package     plugintype_pluginname
 * @subpackage  plugintype_pluginname
 * @category    optional API reference
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns static welcome front page for the page.
 *
 * @return string HTML
 */
function local_amos_frontpage() {
    return '
        <div class="showroom">
            <h2>Welcome everyone!</h2>
            <p>Welcome to our Moodle languages portal, containing <a title="AMOS home page" href="http://lang.moodle.org/local/amos/">AMOS</a>, the Moodle translation tool. See <a title="Moodle Docs - Translation" href="http://docs.moodle.org/en/Translation">Translation</a> for details of the translation process.</p>
        </div>
        <table class="frontpagetable" width="100%">
            <tbody>
                <tr>
                    <td class="frontpageimage c0">
                        <div>
                            <a class="frontpagelink" href="http://lang.moodle.org/local/amos/">
                                <img src="http://lang.moodle.org/theme/moodleofficial/pix/fp/fp_amos.png" alt="AMOS" />
                                <br />
                                AMOS translator
                            </a>
                        </div>
                    </td>
                    <td class="frontpageimage c1">
                        <div>
                            <a class="frontpagelink" href="http://lang.moodle.org/course/view.php?id=2">
                                <img src="http://lang.moodle.org/theme/moodleofficial/pix/fp/fp_discussion.png" alt="Discussion" />
                                <br />
                                Translation forum
                            </a>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="frontpageimage c0">
                        <div>
                            <a class="frontpagelink" href="http://lang.moodle.org/mod/page/view.php?id=9">
                                <img src="http://lang.moodle.org/theme/moodleofficial/pix/fp/fp_help.png" alt="Help" />
                                <br />
                                Help for newcomers
                            </a>
                        </div>
                    </td>
                    <td class="frontpageimage c1">
                        <div>
                            <a class="frontpagelink" href="http://lang.moodle.org/mod/url/view.php?id=16&amp;redirect=1" target="_blank">
                                <img src="http://lang.moodle.org/theme/moodleofficial/pix/fp/fp_manual.png" alt="Manual" />
                                <br />
                                AMOS user manual
                            </a>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>';
}

/**
 * TODO: short description.
 *
 * @return TODO
 */
function local_amos_frontpage_stats() {
    global $CFG, $DB;

    $total = (int)$DB->get_field_sql("
        SELECT SUM(strings)
          FROM {amos_contributions} c
          JOIN mdl_amos_stashes s ON c.stashid = s.id
         WHERE c.status = 30");

    $recent = $DB->get_records_sql("
        SELECT c.authorid AS id, u.lastname, u.firstname, MAX(c.timecreated) AS mostrecent
          FROM {amos_contributions} c
          JOIN {user} u ON u.id = c.authorid
      GROUP BY c.authorid, u.lastname, u.firstname
      ORDER BY mostrecent DESC", null, 0, 4);

    $links = array();
    foreach ($recent as $contributor) {
        $links[] = '<a href="'.$CFG->wwwroot.'/user/profile.php?id='.$contributor->id.'">'.s(fullname($contributor)).'</a>';
    }

    $last = array_pop($links);

    $links = implode(', ', $links) . ' and ' . $last;

    return '
        <div style="text-align:center;margin-botton:30px;">
            Total of <span style="font-size:x-large">'.$total.'</span> strings translated by community members have been submitted into AMOS so far.
        </div>
        <div style="text-align:center;margin:30px;">
        <a style="padding:20px;background-color:#88de85;color:white;font-size:xx-large;font-weight:bold;-webkit-border-radius:7px;-moz-border-radius:7px;border:1px solid green;text-decoration:none;" href="http://lang.moodle.org/local/amos/">
                Contribute now!
            </a>
        </div>
        <div style="text-align:center;margin:30px;">
            Many thanks to '.$links.' for their recent contributions!
        </div>';
}

echo local_amos_frontpage();
echo local_amos_frontpage_stats();

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
 * Displays the lang.moodle.org front page content
 *
 * @package     local_amos
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Populates the contribution front page block contents.
 *
 * @return string
 */
function local_amos_frontpage_contribution_stats() {
    global $CFG, $DB;

    $total = (int)$DB->get_field_sql("
        SELECT SUM(strings)
          FROM {amos_contributions} c
          JOIN mdl_amos_stashes s ON c.stashid = s.id
         WHERE c.status = 30");

    $namefields = get_all_user_name_fields(true, "u");
    $recent = $DB->get_records_sql("
        SELECT c.authorid AS id, $namefields, MAX(c.timecreated) AS mostrecent
          FROM {amos_contributions} c
          JOIN {user} u ON u.id = c.authorid
      GROUP BY c.authorid, $namefields
      ORDER BY mostrecent DESC", null, 0, 4);

    $links = array();
    foreach ($recent as $contributor) {
        $links[] = '<a href="'.$CFG->wwwroot.'/user/profile.php?id='.$contributor->id.'">'.s(fullname($contributor)).'</a>';
    }

    $links = get_string('contributethankslist', 'local_amos', [
        'contributor1' => $links[0],
        'contributor2' => $links[1],
        'contributor3' => $links[2],
        'contributor4' => $links[3],
    ]);

    return '<p>' . get_string('contributestats', 'local_amos', array('count' => $total)) . '</p>
        <div style="text-align:center; margin:1em;">
        <a class="btn btn-large btn-success" href="/local/amos/">' . get_string('contributenow', 'local_amos') . '</a>
        </div>
        <p>' . get_string('contributethanks', 'local_amos', array('listcontributors' => $links)) . '</p>';
}

?>

<div id="amos-custom-front-page" class="container-fluid">
    <div class="row-fluid">
        <div class="span4">
            <div class="well frontpageblock">
                <h2><?php print_string('amos', 'local_amos'); ?></h2>
                <div><?php print_string('about', 'local_amos'); ?></div>
            </div>
        </div>
        <div class="span4">
            <div class="well frontpageblock">
                <h2><?php print_string('contribute', 'local_amos'); ?></h2>
                <?php echo local_amos_frontpage_contribution_stats(); ?>
            </div>
        </div>
        <div class="span4">
            <div class="well frontpageblock">
                <h2><?php print_string('quicklinks', 'local_amos'); ?></h2>
                <ul class="unstyled">
                    <li><a href="/local/amos/view.php"><i class="icon-pencil"></i> <?php print_string('quicklinks_amos', 'local_amos'); ?></a></li>
                    <li><a href="/course/view.php?id=2"><i class="icon-comment"></i> <?php print_string('quicklinks_forum', 'local_amos'); ?></a></li>
                    <li><a href="/mod/page/view.php?id=9"><i class="icon-info-sign"></i> <?php print_string('quicklinks_newcomers', 'local_amos'); ?></a></li>
                    <li><a href="/mod/url/view.php?id=16&amp;redirect=1"><i class="icon-book"></i> <?php print_string('quicklinks_manual', 'local_amos'); ?></a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

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
 * Displays and manages submitted contributions
 *
 * @package    local
 * @subpackage amos
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mlanglib.php');

require_login(SITEID, false);
require_capability('local/amos:stash', get_system_context());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/contrib.php');
navigation_node::override_active_url(new moodle_url('/local/amos/contrib.php'));
$PAGE->set_title(get_string('contributions', 'local_amos'));
$PAGE->set_heading(get_string('contributions', 'local_amos'));
//$PAGE->requires->yui_module('moodle-local_amos-contrib', 'M.local_amos.init_contrib');
//$PAGE->requires->strings_for_js(array('confirmaction'), 'local_amos');

$output = $PAGE->get_renderer('local_amos');

// Output starts here
echo $output->header();

// Maintainer's UI
if (has_capability('local/amos:commit', get_system_context())) {
    $maintenances = $DB->get_records('amos_translators', array('userid' => $USER->id));
    $maintainerof = array();  // list of languages the USER is maintainer of, or 'all'
    foreach ($maintenances as $maintained) {
        if ($maintained->lang === 'X') {
            $maintainerof = 'all';
            break;
        }
        $maintainerof[] = $maintained->lang;
    }

    if (empty($maintainerof)) {
        $contributions = array();

    } else {
        $params = array();

        if (is_array($maintainerof)) {
            list($langsql, $langparams) = $DB->get_in_or_equal($maintainerof);
            $params = array_merge($params, $langparams);
        } else {
            $langsql = "";
        }

        $sql = "SELECT c.id, c.lang, c.subject, c.message, c.stashid, c.status, c.timecreated, c.timemodified,
                       s.components, s.strings,
                       ".user_picture::fields('a', null, 'authorid', 'author').",
                       ".user_picture::fields('m', null, 'assigneeid', 'assignee')."
                  FROM {amos_contributions} c
                  JOIN {amos_stashes} s ON (c.stashid = s.id)
                  JOIN {user} a ON c.authorid = a.id
             LEFT JOIN {user} m ON c.assignee = m.id";

        if ($langsql) {
            $sql .= " WHERE c.lang $langsql";
        }

        $sql .= " ORDER BY c.status, COALESCE (s.timemodified,s.timecreated) DESC";

        $contributions = $DB->get_records_sql($sql, $params);
    }

    if (empty($contributions)) {
        echo $output->heading(get_string('contribincomingnone', 'local_amos'));

    } else {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable contributionlist incoming';
        $table->head = array(
            get_string('contribid', 'local_amos'),
            get_string('contribstatus', 'local_amos'),
            get_string('contribauthor', 'local_amos'),
            get_string('contribsubject', 'local_amos'),
            get_string('contribtimemodified', 'local_amos'),
            get_string('contribassignee', 'local_amos'),
            get_string('language', 'local_amos'),
            get_string('strings', 'local_amos')
        );
        $table->colclasses = array('id', 'status', 'author', 'subject', 'timemodified', 'assignee', 'language', 'strings');

        foreach ($contributions as $contribution) {
            $url = new moodle_url($PAGE->url, array('id' => $contribution->id));
            $cells   = array();
            $cells[] = new html_table_cell(html_writer::link($url, '#'.$contribution->id));
            $status  = get_string('contribstatus'.$contribution->status, 'local_amos');
            $cells[] = new html_table_cell(html_writer::link($url, $status));
            $author  = new user_picture(user_picture::unalias($contribution, null, 'authorid', 'author'));
            $author->size = 16;
            $cells[] = new html_table_cell($output->render($author) . s(fullname($author->user)));
            $cells[] = new html_table_cell(html_writer::link($url, s($contribution->subject)));
            $time    = is_null($contribution->timemodified) ? $contribution->timecreated : $contribution->timemodified;
            $cells[] = new html_table_cell(userdate($time, get_string('strftimedaydatetime', 'langconfig')));
            if (is_null($contribution->assigneeid)) {
                $assignee = get_string('contribassigneenone', 'local_amos');
            } else {
                $assignee = new user_picture(user_picture::unalias($contribution, null, 'assigneeid', 'assignee'));
                $assignee->size = 16;
                $assignee = $output->render($assignee) . s(fullname($assignee->user));
            }
            $cells[] = new html_table_cell($assignee);
            $cells[] = new html_table_cell(s($contribution->lang));
            $cells[] = new html_table_cell(s($contribution->strings));
            $row = new html_table_row($cells);
            $row->attributes['class'] = 'status'.$contribution->status;
            $table->data[] = $row;
        }

        echo $output->heading(get_string('contribincomingsome', 'local_amos', count($contributions)));
        echo html_writer::table($table);
    }
}

echo $output->footer();

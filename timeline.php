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
 * Displays a timeline history of a given string
 *
 * @package    local
 * @subpackage amos
 * @copyright  2010 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
//require_once($CFG->dirroot . '/local/amos/locallib.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/renderer.php');

require_login(SITEID, false);
require_capability('local/amos:stage', get_system_context());

$component  = required_param('component', PARAM_ALPHANUMEXT);
$language   = required_param('language', PARAM_ALPHANUMEXT);
$branch     = required_param('branch', PARAM_INT);
$stringid   = required_param('stringid', PARAM_STRINGID);
$ajax       = optional_param('ajax', 0, PARAM_BOOL);

$PAGE->set_url('/local/amos/timeline.ajax.php');
$PAGE->set_pagelayout('popup');
$PAGE->set_context(get_context_instance(CONTEXT_SYSTEM));

if ($ajax) {
    @header('Content-Type: text/plain; charset=utf-8');
} else {
    echo $OUTPUT->header();
}

$sql = "SELECT s.id, s.lang, s.text, s.timemodified, s.deleted,
               c.userinfo, c.commitmsg, c.commithash
          FROM {amos_repository} s
          JOIN {amos_commits} c ON c.id = s.commitid
         WHERE branch = ? AND (lang = 'en' OR lang = ?) AND component = ? AND stringid = ?
      ORDER BY s.timemodified DESC, s.id DESC";

$params = array($branch, $language, $component, $stringid);

$results = $DB->get_records_sql($sql, $params);

if (!$results) {
    print_error('invalidtimelineparams', 'local_amos');
}

$table = new html_table();
$table->attributes['class'] = 'timelinetable';

foreach ($results as $result) {

    $encell     = new html_table_cell();
    $langcell   = new html_table_cell();
    if ($result->lang == 'en') {
        $cell = $encell;
        $none = $langcell;
    } else {
        $cell = $langcell;
        $none = $encell;
    }

    $date = html_writer::tag('div', local_amos_renderer::commit_datetime($result->timemodified), array('class' => 'timemodified'));
    $userinfo = html_writer::tag('span', s($result->userinfo), array('class' => 'userinfo'));
    $commitmsg = html_writer::tag('span', s($result->commitmsg), array('class' => 'commitmsg'));
    if ($result->deleted) {
        $text = html_writer::tag('del', s($result->text));
    } else {
        $text = s($result->text);
    }
    $text = local_amos_renderer::add_breaks($text);
    if ($result->commithash) {
        $commithash = html_writer::tag('div', $result->commithash, array('class' => 'commithash'));
    } else {
        $commithash = '';
    }
    $text = html_writer::tag('div', $text, array('class' => 'text preformatted'));

    $cell->text = $date . html_writer::tag('div', $userinfo . ' ' . $commitmsg . $commithash, array('class' => 'usermessage')) . $text;
    $none->text = '&nbsp;';

    $row = new html_table_row(array($encell, $langcell));
    $table->data[] = $row;
}

echo html_writer::table($table);

if (!$ajax) {
    echo $OUTPUT->footer();
}

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

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/renderer.php');

require_login(SITEID, false);
require_capability('local/amos:stage', context_system::instance());

$component = required_param('component', PARAM_ALPHANUMEXT);
$language = required_param('language', PARAM_ALPHANUMEXT);
$stringid = required_param('stringid', PARAM_STRINGID);

$PAGE->set_url('/local/amos/timeline.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('timeline', 'local_amos'));
$PAGE->set_context(context_system::instance());

echo $OUTPUT->header();

$sql = "SELECT -s.id AS id, 'en' AS lang, s.strtext, s.since, s.timemodified,
               c.userinfo, c.commitmsg, c.commithash
          FROM {amos_strings} s
          JOIN {amos_commits} c ON c.id = s.commitid
         WHERE component = :component1
           AND strname = :strname1

         UNION

        SELECT s.id, s.lang, s.strtext, s.since, s.timemodified,
               c.userinfo, c.commitmsg, c.commithash
          FROM {amos_translations} s
          JOIN {amos_commits} c ON c.id = s.commitid
         WHERE component = :component2
           AND lang = :lang
           AND strname = :strname2

      ORDER BY since, timemodified, id";

$params = [
    'component1' => $component,
    'component2' => $component,
    'lang' => $language,
    'strname1' => $stringid,
    'strname2' => $stringid,
];

$results = $DB->get_records_sql($sql, $params);

if (!$results) {
    print_error('invalidtimelineparams', 'local_amos');
}

$table = new html_table();
$table->attributes['class'] = 'timelinetable';
$preven = '';
$prevtr = '';

foreach ($results as $result) {
    $encell = new html_table_cell();
    $langcell = new html_table_cell();
    if ($result->lang == 'en') {
        $cell = $encell;
        $none = $langcell;
        $prev = $preven;
    } else {
        $cell = $langcell;
        $none = $encell;
        $prev = $prevtr;
    }

    $since = html_writer::span(mlang_version::by_code($result->since)->label . '+', 'small since');
    $date = html_writer::span(local_amos_renderer::commit_datetime($result->timemodified), 'timemodified');
    $userinfo = html_writer::tag('span', s($result->userinfo), array('class' => 'userinfo'));
    $commitmsg = html_writer::tag('span', s($result->commitmsg), array('class' => 'commitmsg'));
    if ($result->strtext === null) {
        $text = html_writer::tag('del', s($prev));
    } else {
        $text = s($result->strtext);
    }
    $text = \local_amos\local\util::add_breaks($text);
    if ($result->commithash) {
        if ($result->lang == 'en') {
            $url = 'https://github.com/moodle/moodle/commit/'.$result->commithash;
        } else {
            $url = 'https://github.com/mudrd8mz/moodle-lang/commit/'.$result->commithash;
        }
        $hashlink = html_writer::link($url, $result->commithash);
        $commithash = html_writer::tag('div', $hashlink, array('class' => 'commithash'));
    } else {
        $commithash = '';
    }
    $text = html_writer::div($text, 'text preformatted');

    $cell->text = html_writer::div($since . ' | ' . $date) .
        html_writer::div($userinfo . ' ' . $commitmsg . $commithash, 'usermessage') . $text;
    $none->text = '&nbsp;';

    $row = new html_table_row(array($encell, $langcell));
    $table->data[] = $row;

    if ($result->lang == 'en') {
        $preven = $result->strtext;
    } else {
        $prevtr = $result->strtext;
    }
}

$table->data = array_reverse($table->data);

echo html_writer::table($table);

echo $OUTPUT->footer();
